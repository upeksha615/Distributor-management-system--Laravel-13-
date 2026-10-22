<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\SalesService;
use App\Support\Exporter;
use Illuminate\Http\Request;

class SalesOrderController extends Controller
{
    public function __construct(private SalesService $sales) {}

    public function index(Request $request)
    {
        $orders = $this->dealerScope(SalesOrder::with(['customer', 'invoice', 'user']))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->source, fn ($q, $s) => $q->where('source', $s))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w->where('number', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->search($term))))
            ->latest('id');

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'sales-orders', 'Sales orders', [
                'Order' => 'text', 'Date' => 'text', 'Customer' => 'text', 'Status' => 'text', 'Invoice' => 'text', 'Total' => 'money',
            ], $rows = $orders->get()->map(fn ($o) => [
                $o->number, $o->order_date->format('d/m/Y'), $o->customer->name, ucfirst($o->status), $o->invoice?->number, $o->total,
            ]), $this->filterSummary($request), ['Total', null, null, null, null, $rows->sum(fn ($r) => $r[5])]);
        }

        $orders = $orders->paginate(25)->withQueryString();

        return view('sales-orders.index', compact('orders'));
    }

    public function create(Request $request)
    {
        return view('sales-orders.form', $this->formData(new SalesOrder([
            'order_date' => today(),
            'customer_id' => $request->customer_id,
        ])));
    }

    public function store(Request $request)
    {
        $order = $this->sales->saveOrder($this->validated($request));

        return redirect()->route('sales-orders.show', $order)->with('success', "Sales order {$order->number} created.");
    }

    public function show(SalesOrder $salesOrder)
    {
        $this->authorizeDealer($salesOrder->customer);
        $salesOrder->load(['customer', 'warehouse', 'user', 'items.product.stockLevels', 'invoice']);

        return view('sales-orders.show', ['order' => $salesOrder]);
    }

    public function edit(SalesOrder $salesOrder)
    {
        $this->authorizeDealer($salesOrder->customer);
        abort_unless($salesOrder->status === 'pending', 403, 'Only pending orders can be edited.');
        $salesOrder->load('items.product');

        return view('sales-orders.form', $this->formData($salesOrder));
    }

    public function update(Request $request, SalesOrder $salesOrder)
    {
        abort_unless($salesOrder->status === 'pending', 403, 'Only pending orders can be edited.');
        $this->sales->saveOrder($this->validated($request), $salesOrder);

        return redirect()->route('sales-orders.show', $salesOrder)->with('success', 'Sales order updated.');
    }

    public function invoice(Request $request, SalesOrder $salesOrder)
    {
        $request->validate(['invoice_date' => ['required', 'date']]);
        $override = $request->boolean('override_credit') && $request->user()->can('invoices.override_credit');

        $invoice = $this->sales->invoiceFromOrder($salesOrder, $request->invoice_date, $override);

        return redirect()->route('invoices.show', $invoice)->with('success', "Invoice {$invoice->number} created and stock deducted.");
    }

    public function cancel(SalesOrder $salesOrder)
    {
        abort_unless($salesOrder->status === 'pending', 403, 'Only pending orders can be cancelled.');
        $salesOrder->update(['status' => 'cancelled']);

        return back()->with('success', 'Sales order cancelled.');
    }

    private function formData(SalesOrder $order): array
    {
        $default = Warehouse::where('is_active', true)->orderBy('id')->value('id');
        $taxPercent = ((float) $order->subtotal - (float) $order->discount) > 0
            ? round((float) $order->tax / ((float) $order->subtotal - (float) $order->discount) * 100, 2)
            : config('distributor.tax_rate');

        return [
            'order' => $order,
            'customers' => Customer::where('is_active', true)->visibleTo(request()->user())->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'warehouseId' => $order->warehouse_id ?? $default,
            'taxPercent' => $order->exists ? $taxPercent : config('distributor.tax_rate'),
            'items' => $order->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'label' => "{$i->product->sku} - {$i->product->name}",
                'quantity' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'discount_percent' => (float) $i->discount_percent,
                'tax_percent' => (float) $i->tax_percent,
            ])->values(),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate(SalesValidation::rules('order_date'), SalesValidation::messages());
    }
}
