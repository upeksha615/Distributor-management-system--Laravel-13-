<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Services\PayablesService;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SupplierBillController extends Controller
{
    public function __construct(private PayablesService $payables) {}

    public function index(Request $request)
    {
        $bills = SupplierBill::with(['supplier', 'goodsReceipt'])
            ->when($request->supplier_id, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($request->status, fn ($q, $s) => $s === 'overdue'
                ? $q->open()->where('due_date', '<', today())
                : $q->where('status', $s))
            ->when($request->boolean('overdue'), fn ($q) => $q->open()->where('due_date', '<', today()))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w->where('number', 'like', "%{$term}%")
                ->orWhere('supplier_invoice_no', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")))
            ->when($request->from, fn ($q, $d) => $q->where('bill_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('bill_date', '<=', $d))
            ->latest('bill_date')->latest('id');

        if ($format = $this->exportFormat($request)) {
            $rows = $bills->get()->map(fn ($b) => [
                $b->number, $b->bill_date->format('d/m/Y'), $b->supplier->name, $b->supplier_invoice_no, $b->description,
                $b->due_date->format('d/m/Y'), ucfirst($b->status), $b->amount, $b->paid_amount,
                $b->status === 'cancelled' ? 0 : $b->balance(),
            ]);

            return Exporter::download($format, 'supplier-bills', 'Supplier bills', [
                'Bill' => 'text', 'Date' => 'text', 'Supplier' => 'text', 'Supplier invoice' => 'text', 'Description' => 'text',
                'Due' => 'text', 'Status' => 'text', 'Amount' => 'money', 'Paid' => 'money', 'Balance' => 'money',
            ], $rows, $this->filterSummary($request), ['Total', null, null, null, null, null, null,
                $rows->sum(fn ($r) => $r[6] === 'Cancelled' ? 0 : $r[7]), $rows->sum(fn ($r) => $r[8]), $rows->sum(fn ($r) => $r[9])]);
        }

        $bills = $bills->paginate(25)->withQueryString();

        return view('supplier-bills.index', [
            'bills' => $bills,
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** Manual bill for other costs: freight, customs duty, clearing charges, rent... */
    public function create(Request $request)
    {
        return view('supplier-bills.create', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'supplierId' => $request->supplier_id,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'supplier_invoice_no' => ['nullable', 'max:50'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'description' => ['required', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $bill = $this->payables->createBill(Supplier::findOrFail($data['supplier_id']), $data);

        return redirect()->route('supplier-bills.show', $bill)->with('success', "Bill {$bill->number} recorded.");
    }

    public function show(SupplierBill $supplierBill)
    {
        $supplierBill->load(['supplier', 'goodsReceipt.purchaseOrder', 'allocations.payment']);

        return view('supplier-bills.show', ['bill' => $supplierBill]);
    }

    public function cancel(Request $request, SupplierBill $supplierBill)
    {
        $data = $request->validate(['reason' => ['nullable', 'max:255']]);

        $this->payables->cancelBill($supplierBill, $data['reason'] ?? null);

        return back()->with('success', "Bill {$supplierBill->number} cancelled.");
    }

    /** Open supplier bill balances by supplier, bucketed by days past the due date. */
    public function agedCreditors(Request $request)
    {
        $buckets = ReportController::AGE_BUCKETS;

        $bills = SupplierBill::with('supplier')->open()
            ->when($request->supplier_id, fn ($q, $id) => $q->where('supplier_id', $id))
            ->get();

        // Payments not yet applied to any bill (advances), per supplier.
        $unallocated = SupplierPayment::query()
            ->when($request->supplier_id, fn ($q, $id) => $q->where('supplier_id', $id))
            ->groupBy('supplier_id')->selectRaw('supplier_id, SUM(amount) as paid')->pluck('paid', 'supplier_id')
            ->map(fn ($paid, $supplierId) => round((float) $paid - (float) SupplierPaymentAllocation::whereHas('payment', fn ($q) => $q->where('supplier_id', $supplierId))->sum('amount'), 2))
            ->filter(fn ($v) => $v > 0);

        $rows = $bills->groupBy('supplier_id')->map(function ($group) use ($buckets) {
            $row = ['supplier' => $group->first()->supplier, 'total' => 0, 'oldest' => 0] + array_fill_keys(array_keys($buckets), 0);

            foreach ($group as $bill) {
                $days = $bill->daysOverdue();
                $bucket = match (true) {
                    $days <= 0 => 'current',
                    $days <= 30 => 'd30',
                    $days <= 60 => 'd60',
                    $days <= 90 => 'd90',
                    default => 'd90plus',
                };
                $row[$bucket] += $bill->balance();
                $row['total'] += $bill->balance();
                $row['oldest'] = max($row['oldest'], $days);
            }

            return $row;
        });

        // Suppliers with only an advance still appear, so the net figure is complete.
        foreach ($unallocated as $supplierId => $amount) {
            if (! $rows->has($supplierId)) {
                $rows[$supplierId] = ['supplier' => Supplier::find($supplierId), 'total' => 0, 'oldest' => 0] + array_fill_keys(array_keys($buckets), 0);
            }
        }

        $rows = $rows->map(fn ($r, $supplierId) => $r + [
            'advance' => (float) ($unallocated[$supplierId] ?? 0),
            'net' => round($r['total'] - (float) ($unallocated[$supplierId] ?? 0), 2),
        ])->sortByDesc('total')->values();

        $totals = array_fill_keys(array_merge(array_keys($buckets), ['total', 'advance', 'net']), 0);
        foreach ($rows as $row) {
            foreach ($totals as $key => $_) {
                $totals[$key] += $row[$key];
            }
        }

        if ($format = $this->exportFormat($request)) {
            $supplier = $request->supplier_id ? Supplier::find($request->supplier_id)?->name : null;
            $columns = ['Code' => 'text', 'Supplier' => 'text', 'Terms (days)' => 'number']
                + collect($buckets)->mapWithKeys(fn ($label) => [$label => 'money'])->all()
                + ['Total bills' => 'money', 'Unallocated paid' => 'money', 'Net owing' => 'money', 'Oldest (days)' => 'number'];

            return Exporter::download($format, 'aged-creditors', 'Aged creditors', $columns, $rows->map(fn ($r) => array_merge(
                [$r['supplier']->code, $r['supplier']->name, $r['supplier']->payment_terms_days],
                array_map(fn ($k) => $r[$k], array_keys($buckets)),
                [$r['total'], $r['advance'], $r['net'], $r['oldest']],
            )), 'As of '.today()->format('d/m/Y').($supplier ? ' · Supplier: '.$supplier : ''),
                array_merge(['Total', null, null], array_map(fn ($k) => $totals[$k], array_keys($buckets)), [$totals['total'], $totals['advance'], $totals['net'], null]));
        }

        return view('reports.aged-creditors', [
            'rows' => $rows,
            'totals' => $totals,
            'buckets' => $buckets,
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    /** Supplier statement: bills are credits (we owe more), payments are debits. */
    public function statement(Request $request, Supplier $supplier)
    {
        $from = Carbon::parse($request->input('from', today()->subMonths(3)->startOfMonth()))->startOfDay();
        $to = Carbon::parse($request->input('to', today()))->endOfDay();

        $entries = collect();

        foreach ($supplier->bills()->where('status', '!=', 'cancelled')->get() as $bill) {
            $entries->push([
                'date' => $bill->bill_date, 'type' => 'Bill'.($bill->supplier_invoice_no ? ' (inv '.$bill->supplier_invoice_no.')' : ''),
                'number' => $bill->number, 'description' => $bill->description, 'url' => route('supplier-bills.show', $bill),
                'debit' => 0, 'credit' => (float) $bill->amount,
            ]);
        }
        foreach ($supplier->payments()->get() as $p) {
            $entries->push([
                'date' => $p->payment_date, 'type' => 'Payment ('.$p->methodLabel().')', 'number' => $p->number,
                'description' => $p->cheque_number ? 'Cheque '.$p->cheque_number : $p->reference, 'url' => route('supplier-payments.show', $p),
                'debit' => (float) $p->amount, 'credit' => 0,
            ]);
        }

        $entries = $entries->sortBy(fn ($e) => $e['date']->format('Ymd').$e['number'])->values();
        $opening = $entries->filter(fn ($e) => $e['date']->lt($from))->sum(fn ($e) => $e['credit'] - $e['debit']);

        $running = $opening;
        $lines = $entries->filter(fn ($e) => $e['date']->between($from, $to))->map(function ($e) use (&$running) {
            $running += $e['credit'] - $e['debit'];

            return $e + ['balance' => $running];
        })->values();

        if ($format = $this->exportFormat($request)) {
            $period = $from->format('d/m/Y').' - '.$to->format('d/m/Y');
            $columns = ['Date' => 'text', 'Type' => 'text', 'Reference' => 'text', 'Details' => 'text', 'Debit (paid)' => 'money', 'Credit (billed)' => 'money', 'Balance' => 'money'];
            $rows = collect([[$from->format('d/m/Y'), 'Opening balance', '', '', null, null, $opening]])
                ->concat($lines->map(fn ($l) => [$l['date']->format('d/m/Y'), $l['type'], $l['number'], $l['description'], $l['debit'] ?: null, $l['credit'] ?: null, $l['balance']]));
            $name = 'supplier-statement-'.$supplier->code;

            if ($format === 'pdf') {
                return Exporter::pdfView('pdf.document', [
                    'docTitle' => 'SUPPLIER STATEMENT',
                    'docNumber' => $period,
                    'party' => ['label' => 'Supplier', 'lines' => [$supplier->name.' ('.$supplier->code.')', $supplier->address, $supplier->country, trim($supplier->phone.' '.$supplier->email)]],
                    'meta' => ['Statement date' => today()->format('d/m/Y'), 'Period' => $period, 'Terms' => ($supplier->payment_terms_days ?? 30).' days'],
                    'columns' => $columns,
                    'rows' => $rows->all(),
                    'totals' => ['Opening balance' => $opening, 'Closing balance (we owe)' => $running],
                ], $name);
            }

            return Exporter::download($format, $name, 'Supplier statement - '.$supplier->name, $columns, $rows, $period,
                ['Closing balance', null, null, null, null, null, $running]);
        }

        return view('suppliers.statement', compact('supplier', 'from', 'to', 'opening', 'lines', 'running'));
    }
}
