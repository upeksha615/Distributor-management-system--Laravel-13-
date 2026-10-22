<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\PayablesService;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierPaymentController extends Controller
{
    public function __construct(private PayablesService $payables) {}

    public function index(Request $request)
    {
        $payments = SupplierPayment::with('supplier')
            ->when($request->supplier_id, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($request->method, fn ($q, $m) => $q->where('method', $m))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w->where('number', 'like', "%{$term}%")
                ->orWhere('cheque_number', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"))))
            ->when($request->from, fn ($q, $d) => $q->where('payment_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('payment_date', '<=', $d))
            ->latest('payment_date')->latest('id');

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'supplier-payments', 'Payments to suppliers', [
                'Voucher' => 'text', 'Date' => 'text', 'Supplier' => 'text', 'Method' => 'text', 'Cheque no' => 'text',
                'Cheque date' => 'text', 'Reference' => 'text', 'Amount' => 'money',
            ], $rows = $payments->get()->map(fn ($p) => [
                $p->number, $p->payment_date->format('d/m/Y'), $p->supplier->name, $p->methodLabel(), $p->cheque_number,
                $p->cheque_date?->format('d/m/Y'), $p->reference, $p->amount,
            ]), $this->filterSummary($request), ['Total paid', null, null, null, null, null, null, $rows->sum(fn ($r) => $r[7])]);
        }

        $payments = $payments->paginate(25)->withQueryString();

        return view('supplier-payments.index', [
            'payments' => $payments,
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request)
    {
        return view('supplier-payments.create', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'supplierId' => $request->supplier_id,
        ]);
    }

    /** JSON list of a supplier's open bills for the allocation table. */
    public function openBills(Supplier $supplier)
    {
        return [
            'balance' => $supplier->balance(),
            'bills' => $supplier->bills()->open()->orderBy('due_date')->orderBy('id')->get()->map(fn ($b) => [
                'id' => $b->id,
                'number' => $b->number,
                'supplier_invoice_no' => $b->supplier_invoice_no,
                'description' => $b->description,
                'bill_date' => $b->bill_date->format('Y-m-d'),
                'due_date' => $b->due_date->format('Y-m-d'),
                'amount' => (float) $b->amount,
                'balance' => $b->balance(),
                'overdue' => $b->daysOverdue(),
            ]),
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'payment_date' => ['required', 'date'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'max:100'],
            'cheque_number' => ['required_if:method,cheque', 'nullable', 'max:50'],
            'cheque_date' => ['required_if:method,cheque', 'nullable', 'date'],
            'notes' => ['nullable', 'max:2000'],
            'allocation_mode' => ['required', 'in:auto,manual,none'],
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $supplier = Supplier::findOrFail($data['supplier_id']);
        $allocations = $data['allocation_mode'] === 'manual' ? ($data['allocations'] ?? []) : [];

        $payment = $this->payables->recordPayment($supplier, $data, $allocations, $data['allocation_mode'] === 'auto');

        return redirect()->route('supplier-payments.show', $payment)->with('success', "Payment {$payment->number} recorded.");
    }

    public function show(SupplierPayment $supplierPayment)
    {
        $payment = $supplierPayment->load(['supplier', 'user', 'allocations.bill']);
        $openBills = $payment->unallocatedAmount() > 0
            ? $payment->supplier->bills()->open()->orderBy('due_date')->orderBy('id')->get()
            : collect();

        return view('supplier-payments.show', compact('payment', 'openBills'));
    }

    /** Apply the unallocated part of a supplier payment to bills. */
    public function allocate(Request $request, SupplierPayment $supplierPayment)
    {
        $data = $request->validate(['allocations' => ['required', 'array'], 'allocations.*' => ['nullable', 'numeric', 'min:0']]);

        $this->payables->allocate($supplierPayment, array_filter($data['allocations'], fn ($a) => (float) $a > 0));

        return back()->with('success', 'Payment allocated.');
    }

    public function pdf(SupplierPayment $supplierPayment)
    {
        $payment = $supplierPayment->load(['supplier', 'allocations.bill', 'user']);
        $s = $payment->supplier;

        $rows = $payment->allocations->map(fn ($a) => [
            $a->bill->number, $a->bill->supplier_invoice_no, $a->bill->description, $a->bill->bill_date->format('d/m/Y'), $a->amount,
        ])->all();
        if (($left = $payment->unallocatedAmount()) > 0) {
            $rows[] = ['On account (unallocated)', '', '', '', $left];
        }

        return Exporter::pdfView('pdf.document', [
            'docTitle' => 'PAYMENT VOUCHER',
            'docNumber' => $payment->number,
            'party' => ['label' => 'Paid to', 'lines' => [$s->name.' ('.$s->code.')', $s->address, $s->country, trim($s->phone.' '.$s->email)]],
            'meta' => array_filter([
                'Date' => $payment->payment_date->format('d/m/Y'),
                'Method' => $payment->methodLabel(),
                'Cheque no' => $payment->cheque_number,
                'Cheque date' => $payment->cheque_date?->format('d/m/Y'),
                'Reference' => $payment->reference,
                'Prepared by' => $payment->user?->name,
            ]),
            'columns' => ['Bill' => 'text', 'Supplier invoice' => 'text', 'Description' => 'text', 'Bill date' => 'text', 'Amount' => 'money'],
            'rows' => $rows,
            'totals' => ['Amount paid' => $payment->amount],
            'notes' => $payment->notes,
            'signatures' => ['Prepared by', 'Approved by', 'Received by'],
        ], str('Payment voucher '.$payment->number)->slug());
    }
}
