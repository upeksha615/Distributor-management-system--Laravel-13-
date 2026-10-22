<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\Warehouse;
use App\Services\QuotationService;
use App\Support\Exporter;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    private const PDF_COLUMNS = ['#' => 'number', 'SKU' => 'text', 'Description' => 'text', 'Qty' => 'qty', 'Price' => 'money', 'Disc %' => 'qty', 'Total' => 'money'];

    public function __construct(private QuotationService $quotes) {}

    public function index(Request $request)
    {
        $quotes = $this->dealerScope(Quotation::with(['customer', 'salesOrder', 'invoice']))
            ->when($request->status, fn ($q, $s) => match ($s) {
                'expired' => $q->where('status', 'open')->whereDate('valid_until', '<', today()),
                'open' => $q->where('status', 'open')->where(fn ($w) => $w->whereNull('valid_until')->orWhereDate('valid_until', '>=', today())),
                default => $q->where('status', $s),
            })
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w->where('number', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->search($term))))
            ->latest('id');

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'quotations', 'Quotations', [
                'Quote' => 'text', 'Date' => 'text', 'Valid until' => 'text', 'Customer' => 'text', 'Status' => 'text', 'Converted to' => 'text', 'Total' => 'money',
            ], $rows = $quotes->get()->map(fn ($o) => [
                $o->number, $o->quote_date->format('d/m/Y'), $o->valid_until?->format('d/m/Y'), $o->customer->name,
                $o->isExpired() ? 'Expired' : ucfirst($o->status), $o->salesOrder?->number ?? $o->invoice?->number, $o->total,
            ]), $this->filterSummary($request), ['Total', null, null, null, null, null, $rows->sum(fn ($r) => $r[6])]);
        }

        $quotes = $quotes->paginate(25)->withQueryString();

        return view('quotations.index', compact('quotes'));
    }

    public function create(Request $request)
    {
        return view('quotations.form', $this->formData(new Quotation([
            'quote_date' => today(),
            'valid_until' => today()->addDays(QuotationService::VALID_DAYS),
            'customer_id' => $request->customer_id,
        ])));
    }

    public function store(Request $request)
    {
        $quote = $this->quotes->save($this->validated($request));

        return redirect()->route('quotations.show', $quote)->with('success', "Quotation {$quote->number} created.");
    }

    public function show(Quotation $quotation)
    {
        $this->authorizeDealer($quotation->customer);
        $quotation->load(['customer', 'warehouse', 'user', 'items.product', 'salesOrder', 'invoice']);

        return view('quotations.show', ['quote' => $quotation]);
    }

    public function edit(Quotation $quotation)
    {
        $this->authorizeDealer($quotation->customer);
        abort_unless($quotation->status === 'open', 403, 'Only open quotations can be edited.');
        $quotation->load('items.product');

        return view('quotations.form', $this->formData($quotation));
    }

    public function update(Request $request, Quotation $quotation)
    {
        abort_unless($quotation->status === 'open', 403, 'Only open quotations can be edited.');
        $this->quotes->save($this->validated($request), $quotation);

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation updated.');
    }

    public function status(Request $request, Quotation $quotation)
    {
        $request->validate(['status' => ['required', 'in:accepted,rejected']]);
        abort_unless(in_array($quotation->status, ['open', 'accepted', 'rejected'], true), 403, 'This quotation has already been converted.');
        $quotation->update(['status' => $request->status]);

        return back()->with('success', "Quotation marked {$request->status}.");
    }

    public function convertToOrder(Quotation $quotation)
    {
        $order = $this->quotes->convertToOrder($quotation);

        return redirect()->route('sales-orders.show', $order)->with('success', "Sales order {$order->number} created from quotation {$quotation->number}.");
    }

    public function convertToInvoice(Request $request, Quotation $quotation)
    {
        $override = $request->boolean('override_credit') && $request->user()->can('invoices.override_credit');
        $invoice = $this->quotes->convertToInvoice($quotation, $override);

        return redirect()->route('invoices.show', $invoice)->with('success', "Invoice {$invoice->number} created from quotation {$quotation->number} and stock deducted.");
    }

    public function pdf(Quotation $quotation)
    {
        $q = $quotation->load(['customer', 'items.product']);
        $c = $q->customer;

        return Exporter::pdfView('pdf.document', [
            'docTitle' => 'QUOTATION',
            'docNumber' => $q->number,
            'party' => ['label' => 'Customer', 'lines' => [
                $c->name.' ('.$c->code.')',
                trim($c->address.' '.$c->city),
                $c->phone,
                $c->tax_number ? 'Tax no: '.$c->tax_number : null,
            ]],
            'meta' => array_filter([
                'Quote date' => $q->quote_date->format('d/m/Y'),
                'Valid until' => $q->valid_until?->format('d/m/Y'),
                'Customer code' => $c->code,
            ]),
            'columns' => self::PDF_COLUMNS,
            'rows' => $q->items->values()->map(fn ($i, $n) => [
                $n + 1, $i->product->sku, $i->product->name, $i->quantity, $i->unit_price,
                $i->discount_percent > 0 ? $i->discount_percent : null, $i->line_total,
            ])->all(),
            'totals' => array_filter([
                'Subtotal' => $q->subtotal,
                'Discount' => $q->discount > 0 ? -$q->discount : null,
                'Tax' => $q->tax > 0 ? $q->tax : null,
            ], fn ($v) => $v !== null) + ['Total' => $q->total],
            'notes' => $q->notes,
            'signatures' => ['Authorised signature'],
        ], (string) str('Quotation '.$q->number)->slug());
    }

    private function formData(Quotation $quote): array
    {
        $default = Warehouse::where('is_active', true)->orderBy('id')->value('id');

        return [
            'quote' => $quote,
            'customers' => Customer::where('is_active', true)->visibleTo(request()->user())->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'warehouseId' => $quote->warehouse_id ?? $default,
            'taxPercent' => $quote->exists ? QuotationService::taxPercent($quote, 2) : config('distributor.tax_rate'),
            'items' => $quote->items->map(fn ($i) => [
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
        return $request->validate(SalesValidation::rules('quote_date') + [
            'valid_until' => ['nullable', 'date', 'after_or_equal:quote_date'],
        ], SalesValidation::messages());
    }
}
