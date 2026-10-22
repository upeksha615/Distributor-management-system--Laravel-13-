<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\ShipmentService;
use App\Support\Exporter;
use App\Support\SpreadsheetReader;
use Illuminate\Http\Request;

/** Upcoming shipments: uploaded from a file, shown to reps as "Ship", added to stock only when received. */
class ShipmentController extends Controller
{
    public function __construct(private ShipmentService $shipments) {}

    public function index(Request $request)
    {
        $status = $request->input('status', 'open');
        $shipments = Shipment::with(['supplier', 'warehouse', 'user'])->withCount('items')
            ->withSum('items as total_qty', 'quantity')->withSum('items as received_qty', 'received_quantity')
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['pending', 'partial']))
            ->when($status && $status !== 'open' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('number', 'like', "%{$t}%")->orWhere('reference', 'like', "%{$t}%")))
            ->orderByRaw("FIELD(status, 'pending', 'partial', 'received', 'cancelled')")->orderBy('expected_date')
            ->paginate(25)->withQueryString();

        return view('shipments.index', compact('shipments', 'status'));
    }

    public function create()
    {
        return view('shipments.create', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function template(Request $request)
    {
        return Exporter::download($request->query('format') === 'csv' ? 'csv' : 'xlsx', 'shipment-template', 'Upcoming shipment',
            ['product_code' => 'text', 'quantity' => 'qty', 'unit_cost' => 'money'],
            [['BOS-0986-4521', 120, 650], ['NGK-BKR6E-11', 300, '']]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'reference' => ['nullable', 'max:100'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'expected_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'max:1000'],
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,csv,txt'],
        ], ['file.mimes' => 'Upload an Excel (.xlsx) or CSV file.']);

        $file = $request->file('file');
        try {
            $rows = SpreadsheetReader::rows($file->getRealPath(), strtolower($file->getClientOriginalExtension()) === 'csv' ? 'csv' : 'xlsx');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not read the file. Save it as .xlsx or .csv and try again.');
        }

        [$lines, $errors] = $this->shipments->parseRows($rows);
        if ($errors) {
            return back()->withInput()->with(['shipment_errors' => $errors, 'error' => count($errors).' row(s) have problems. Nothing was saved. Fix them and upload again.']);
        }

        $shipment = $this->shipments->create($data + ['source_file' => $file->getClientOriginalName()], $lines);

        return redirect()->route('shipments.show', $shipment)->with('success', "Shipment {$shipment->number} saved with ".count($lines).' product(s). Reps now see it in the Ship column. Stock is not changed until you receive it.');
    }

    public function show(Shipment $shipment)
    {
        $shipment->load(['supplier', 'warehouse', 'user', 'items.product.brand']);

        return view('shipments.show', compact('shipment'));
    }

    public function receive(Request $request, Shipment $shipment)
    {
        $data = $request->validate(['quantities' => ['required', 'array'], 'quantities.*' => ['nullable', 'numeric', 'min:0'], 'note' => ['nullable', 'max:255']]);
        $this->shipments->receive($shipment, $data['quantities'], $data['note'] ?? null);

        return back()->with('success', 'Received quantities added to stock.');
    }

    public function cancel(Request $request, Shipment $shipment)
    {
        $this->shipments->cancel($shipment, $request->validate(['reason' => ['required', 'max:255']])['reason']);

        return back()->with('success', 'Shipment cancelled.');
    }
}
