<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use App\Support\Exporter;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(private PurchaseService $purchases) {}

    public function index(Request $request)
    {
        $orders = PurchaseOrder::with(['supplier', 'warehouse'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->supplier_id, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($request->q, fn ($q, $term) => $q->where('number', 'like', "%{$term}%"))
            ->latest('id');

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'purchase-orders', 'Purchase orders', [
                'PO' => 'text', 'Date' => 'text', 'Supplier' => 'text', 'Warehouse' => 'text', 'Expected' => 'text', 'Status' => 'text', 'Total' => 'money',
            ], $rows = $orders->get()->map(fn ($po) => [
                $po->number, $po->order_date->format('d/m/Y'), $po->supplier->name, $po->warehouse->name,
                $po->expected_date?->format('d/m/Y'), ucfirst($po->status), $po->total,
            ]), $this->filterSummary($request), ['Total', null, null, null, null, null, $rows->sum(fn ($r) => $r[6])]);
        }

        $orders = $orders->paginate(25)->withQueryString();

        return view('purchase-orders.index', [
            'orders' => $orders,
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('purchase-orders.form', $this->formData(new PurchaseOrder([
            'order_date' => today(),
            'supplier_id' => $request->supplier_id,
        ])));
    }

    public function store(Request $request)
    {
        $po = $this->purchases->save($this->validated($request));

        return redirect()->route('purchase-orders.show', $po)->with('success', "Purchase order {$po->number} saved as draft.");
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'warehouse', 'user', 'items.product', 'receipts']);

        return view('purchase-orders.show', ['po' => $purchaseOrder]);
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->isEditable(), 403, 'Only draft purchase orders can be edited.');
        $purchaseOrder->load('items.product');

        return view('purchase-orders.form', $this->formData($purchaseOrder));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->isEditable(), 403, 'Only draft purchase orders can be edited.');
        $this->purchases->save($this->validated($request), $purchaseOrder);

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order updated.');
    }

    public function markOrdered(PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->status === 'draft', 403);
        $purchaseOrder->update(['status' => 'ordered']);

        return back()->with('success', 'Purchase order marked as sent to supplier.');
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->receipts()->exists()) {
            return back()->with('error', 'Goods have already been received on this PO, so it cannot be cancelled.');
        }
        $purchaseOrder->update(['status' => 'cancelled']);

        return back()->with('success', 'Purchase order cancelled.');
    }

    private function formData(PurchaseOrder $po): array
    {
        return [
            'po' => $po,
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'items' => $po->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'label' => "{$i->product->sku} - {$i->product->name}",
                'quantity' => (float) $i->quantity,
                'unit_cost' => (float) $i->unit_cost,
            ])->values(),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ], ['items.required' => 'Add at least one product.']);
    }
}
