<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Warehouse;
use App\Services\SalesService;
use App\Support\Exporter;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private SalesService $sales) {}

    public function index(Request $request)
    {
        $invoices = $this->dealerScope(Invoice::with('customer'))
            ->when($request->status === 'overdue', fn ($q) => $q->open()->where('due_date', '<', today()))
            ->when($request->status && $request->status !== 'overdue', fn ($q) => $q->where('status', $request->status))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w->where('number', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->search($term))))
            ->when($request->from, fn ($q, $d) => $q->where('invoice_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('invoice_date', '<=', $d))
            ->latest('id');

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'invoices', 'Invoices', [
                'Invoice' => 'text', 'Date' => 'text', 'Customer code' => 'text', 'Customer' => 'text', 'Due' => 'text', 'Status' => 'text',
                'Total' => 'money', 'Paid' => 'money', 'Credited' => 'money', 'Balance' => 'money',
            ], $rows = $invoices->get()->map(fn ($i) => [
                $i->number, $i->invoice_date->format('d/m/Y'), $i->customer->code, $i->customer->name, $i->due_date->format('d/m/Y'),
                ucfirst($i->status), $i->total, $i->paid_amount, $i->credited_amount, $i->status === 'cancelled' ? 0 : $i->balance(),
            ]), $this->filterSummary($request), array_merge(['Total', null, null, null, null, null],
                array_map(fn ($c) => $rows->sum(fn ($r) => $r[5] === 'Cancelled' ? 0 : $r[$c]), [6, 7, 8, 9])));
        }

        $invoices = $invoices->paginate(25)->withQueryString();

        return view('invoices.index', compact('invoices'));
    }

    public function create(Request $request)
    {
        return view('invoices.form', [
            'customers' => Customer::where('is_active', true)->visibleTo(request()->user())->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'warehouseId' => Warehouse::where('is_active', true)->orderBy('id')->value('id'),
            'customerId' => $request->customer_id,
            'taxPercent' => config('distributor.tax_rate'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(SalesValidation::rules('invoice_date'), SalesValidation::messages());
        $override = $request->boolean('override_credit') && $request->user()->can('invoices.override_credit');

        $invoice = $this->sales->createInvoice($data, null, $override);

        return redirect()->route('invoices.show', $invoice)->with('success', "Invoice {$invoice->number} created and stock deducted.");
    }

    public function show(Invoice $invoice)
    {
        $this->authorizeDealer($invoice->customer);
        $invoice->load(['customer', 'warehouse', 'user', 'salesOrder', 'items.product', 'allocations.payment', 'creditNotes']);

        return view('invoices.show', compact('invoice'));
    }

    public function cancel(Invoice $invoice)
    {
        abort_unless(request()->user()->can('invoices.cancel'), 403, 'You are not allowed to cancel invoices.');
        $this->sales->cancelInvoice($invoice);

        return back()->with('success', "Invoice {$invoice->number} cancelled and stock returned.");
    }
}
