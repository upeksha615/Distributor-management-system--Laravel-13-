<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\CompanyBankAccount;
use App\Models\Customer;
use App\Models\DocumentAttachment;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Dealer payments: entry (office admin), verification workspace (accountant), allocations, reversal. */
class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function index(Request $request)
    {
        $payments = $this->dealerScope(Payment::with(['customer', 'bankAccount.bank']))
            ->when($request->method, fn ($q, $m) => $q->where('method', $m))
            ->when($request->status === 'awaiting', fn ($q) => $q->awaitingVerification())
            ->when($request->status && $request->status !== 'awaiting', fn ($q) => $q->where('status', $request->status))
            ->when($request->channel, fn ($q, $c) => $q->where('channel', $c))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w->where('number', 'like', "%{$term}%")
                ->orWhere('cheque_number', 'like', "%{$term}%")->orWhere('reference', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->search($term))))
            ->when($request->from, fn ($q, $d) => $q->where('payment_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('payment_date', '<=', $d))
            ->latest('id');

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'payments', 'Dealer payments', [
                'Receipt' => 'text', 'Date' => 'text', 'Dealer' => 'text', 'Method' => 'text', 'Channel' => 'text', 'Reference / cheque' => 'text',
                'Into account' => 'text', 'Status' => 'text', 'Verified by' => 'text', 'Amount' => 'money',
            ], $rows = $payments->with('verifier')->get()->map(fn ($p) => [
                $p->number, $p->payment_date->format('d/m/Y'), $p->customer->name, $p->methodLabel(), Payment::CHANNELS[$p->channel] ?? $p->channel,
                $p->method === 'cheque' ? $p->cheque_number : $p->reference, $p->bankAccount?->label(), $p->displayStatus(), $p->verifier?->name, $p->amount,
            ]), $this->filterSummary($request), ['Total', null, null, null, null, null, null, null, null, $rows->sum(fn ($r) => $r[9])]);
        }

        return view('payments.index', ['payments' => $payments->paginate(25)->withQueryString()]);
    }

    public function create(Request $request)
    {
        return view('payments.create', [
            'customers' => Customer::where('is_active', true)->visibleTo($request->user())->orderBy('name')->get(),
            'customerId' => $request->customer_id,
        ] + $this->formData());
    }

    /** JSON list of a dealer's open invoices for the allocation table. */
    public function openInvoices(Customer $customer)
    {
        $this->authorizeDealer($customer);

        return [
            'balance' => $customer->outstandingBalance(),
            'pending' => $customer->pendingPaymentsTotal(),
            'invoices' => $customer->invoices()->open()->orderBy('due_date')->get()->map(fn ($i) => [
                'id' => $i->id,
                'number' => $i->number,
                'invoice_date' => $i->invoice_date->format('Y-m-d'),
                'due_date' => $i->due_date->format('Y-m-d'),
                'total' => (float) $i->total,
                'balance' => $i->balance(),
                'pending' => $i->pendingAmount(),
                'overdue' => $i->daysOverdue(),
            ]),
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate(self::rules() + [
            'customer_id' => ['required', 'exists:customers,id'],
            'allocation_mode' => ['required', 'in:auto,manual,none'],
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['nullable', 'numeric', 'min:0'],
            'save_as' => ['nullable', 'in:draft,pending_verification'],
            'slip' => DocumentAttachment::rules(),
        ]);

        $customer = Customer::findOrFail($data['customer_id']);
        $this->authorizeDealer($customer);
        $allocations = $data['allocation_mode'] === 'manual' ? ($data['allocations'] ?? []) : [];

        $payment = DB::transaction(function () use ($customer, $data, $allocations, $request) {
            $payment = $this->payments->record($customer, $data + ['status' => $data['save_as'] ?? 'pending_verification', 'channel' => 'office'],
                $allocations, $data['allocation_mode'] === 'auto');
            if ($request->hasFile('slip')) {
                DocumentAttachment::store($payment, $request->file('slip'), $data['method'] === 'cheque' ? 'cheque_image' : 'slip');
                $this->payments->flagDuplicates($payment->fresh());
            }

            return $payment;
        });

        return redirect()->route('payments.show', $payment)->with('success', "Payment {$payment->number} saved as ".strtolower($payment->fresh()->statusLabel())
            .'. It does not reduce the dealer balance until an accountant verifies it'.($payment->method === 'cheque' ? ' and the cheque clears' : '').'.');
    }

    /** Payment detail and verification workspace. */
    public function show(Payment $payment)
    {
        $this->authorizeDealer($payment->customer);
        $payment->load(['customer', 'user', 'verifier', 'reverser', 'allocations.invoice', 'attachments', 'history.user', 'bankAccount.bank', 'bank', 'branch', 'duplicateOf', 'submission']);

        return view('payments.show', [
            'payment' => $payment,
            'duplicates' => $payment->duplicate_check === 'flagged' ? $this->payments->findDuplicates($payment)->load('customer') : collect(),
            'openInvoices' => ! in_array($payment->status, ['rejected', 'reversed']) && $payment->unallocatedAmount() > 0
                ? $payment->customer->invoices()->open()->orderBy('due_date')->get() : collect(),
            'previous' => $payment->customer->payments()->where('id', '!=', $payment->id)->latest('id')->limit(8)->get(),
            'balance' => $payment->customer->outstandingBalance(),
            'canVerify' => $this->verifyBlocker($payment),
        ]);
    }

    /** Apply the unallocated part of a payment to invoices (proposal before verification). */
    public function allocate(Request $request, Payment $payment)
    {
        $this->authorizeDealer($payment->customer);
        abort_unless($payment->isOpen() ? $request->user()->can('payments.record') : $request->user()->can('payments.allocate'), 403);
        $data = $request->validate(['allocations' => ['required', 'array'], 'allocations.*' => ['nullable', 'numeric', 'min:0']]);

        $this->payments->allocate($payment, array_filter($data['allocations'], fn ($a) => (float) $a > 0));

        return back()->with('success', 'Allocation saved.');
    }

    public function reallocate(Request $request, Payment $payment)
    {
        $this->authorizeDealer($payment->customer);
        abort_unless($payment->isOpen() ? $request->user()->can('payments.record') : $request->user()->can('payments.allocate'), 403);
        $data = $request->validate(['allocations' => ['array'], 'allocations.*' => ['nullable', 'numeric', 'min:0'], 'reason' => ['required', 'max:255']]);

        $this->payments->reallocate($payment, $data['allocations'] ?? [], $request->user(), $data['reason']);

        return back()->with('success', 'Allocations replaced.');
    }

    public function action(Request $request, Payment $payment, string $action)
    {
        $this->authorizeDealer($payment->customer);
        $user = $request->user();
        $note = $request->validate(['note' => [in_array($action, ['reject', 'reverse', 'request-info', 'not-duplicate', 'reject-duplicate']) ? 'required' : 'nullable', 'max:255']])['note'] ?? null;

        $permission = ['submit' => 'payments.record', 'verify' => 'payments.verify', 'reject' => 'payments.verify', 'reject-duplicate' => 'payments.record',
            'request-info' => 'payments.verify', 'reverse' => 'payments.reverse', 'not-duplicate' => 'payments.verify'][$action] ?? abort(404);
        abort_unless($user->can($permission), 403, 'You are not allowed to do this.');

        match ($action) {
            'submit' => $this->payments->submitForVerification($payment, $user),
            'verify' => $this->payments->verify($payment, $user, $note),
            'reject' => $this->payments->reject($payment, $user, $note),
            'reject-duplicate' => $this->payments->reject($payment, $user, $note, true),
            'request-info' => $this->payments->requestInformation($payment, $user, $note),
            'reverse' => $this->payments->reverse($payment, $user, $note),
            'not-duplicate' => $this->payments->clearDuplicate($payment, $user, $note),
        };
        $messages = ['submit' => 'Submitted for verification.', 'verify' => 'Payment verified.', 'reject' => 'Payment rejected.', 'reject-duplicate' => 'Rejected as a duplicate.',
            'request-info' => 'Marked as under review: information requested.', 'reverse' => 'Payment reversed; its invoices are open again.', 'not-duplicate' => 'Duplicate warning cleared.'];

        return back()->with('success', $messages[$action]);
    }

    public function uploadAttachment(Request $request, Payment $payment)
    {
        $this->authorizeDealer($payment->customer);
        abort_unless($request->user()->can('payments.record'), 403);
        $data = $request->validate(['file' => DocumentAttachment::rules(true), 'kind' => ['required', Rule::in(array_keys(DocumentAttachment::KINDS))]]);
        DocumentAttachment::store($payment, $request->file('file'), $data['kind']);
        $this->payments->flagDuplicates($payment);

        return back()->with('success', 'Document attached.');
    }

    /** Serve a private attachment to users allowed to see the payment (or submission). */
    public function attachment(Request $request, DocumentAttachment $attachment)
    {
        $owner = $attachment->attachable;
        abort_unless($owner && $request->user()->can('payments.view'), 403);
        // Files of a dealer are visible to users who may see that dealer; unmatched inbox items only to users who see all dealers.
        $owner->customer ? $this->authorizeDealer($owner->customer) : abort_unless($request->user()->canSeeAllDealers(), 403);
        abort_unless($attachment->exists(), 404, 'The file is missing from storage.');

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.addslashes($attachment->original_name).'"',
        ]);
    }

    /** Why the current user cannot verify (null = can verify). */
    private function verifyBlocker(Payment $payment): ?string
    {
        $user = request()->user();
        if (! $payment->isOpen() || ! $user->can('payments.verify')) {
            return 'no';
        }
        if (config('distributor.payment_segregation') && $payment->user_id === $user->id) {
            return 'You entered this payment; another authorised person must verify it.';
        }
        $limit = (float) config('distributor.payment_verify_limit');
        if ($limit > 0 && (float) $payment->amount > $limit && ! $user->can('payments.verify_above_limit')) {
            return 'Above your verification limit of '.money($limit).'. A Finance Manager must verify it.';
        }
        if ($payment->duplicate_check === 'flagged') {
            return 'Possible duplicate: review the warning first.';
        }

        return null;
    }

    public static function rules(): array
    {
        return [
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'reference' => ['nullable', 'max:100', 'required_if:method,bank_transfer,bank_deposit'],
            'company_bank_account_id' => ['nullable', 'required_if:method,bank_transfer,bank_deposit', Rule::exists('company_bank_accounts', 'id')->where('is_active', true)],
            'bank_id' => ['nullable', 'required_if:method,cheque', 'exists:banks,id'],
            'bank_branch_id' => ['nullable', Rule::exists('bank_branches', 'id')->where('bank_id', request('bank_id'))],
            'cheque_number' => ['required_if:method,cheque', 'nullable', 'max:50'],
            'cheque_date' => ['required_if:method,cheque', 'nullable', 'date'],
            'notes' => ['nullable', 'max:2000'],
        ];
    }

    private function formData(): array
    {
        return [
            'banks' => Bank::where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_name']),
            'accounts' => CompanyBankAccount::with('bank')->where('is_active', true)->where('receives_dealer_payments', true)->get(),
        ];
    }
}
