<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\CompanyBankAccount;
use App\Models\Customer;
use App\Models\DocumentAttachment;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\PaymentInboxService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Office Admin: central inbox for payment slips from every channel. */
class PaymentInboxController extends Controller
{
    public function __construct(private PaymentInboxService $inbox) {}

    public function index(Request $request)
    {
        $tab = in_array($request->tab, ['open', 'mine', 'converted', 'rejected', 'all']) ? $request->tab : 'open';
        $items = PaymentSubmission::with(['customer', 'assignee', 'payment'])->withCount('attachments')
            ->when($tab === 'open', fn ($q) => $q->whereIn('status', ['new', 'matched', 'info_requested']))
            ->when($tab === 'mine', fn ($q) => $q->whereIn('status', ['new', 'matched', 'info_requested'])->where('assigned_to', $request->user()->id))
            ->when($tab === 'converted', fn ($q) => $q->where('status', 'converted'))
            ->when($tab === 'rejected', fn ($q) => $q->whereIn('status', ['rejected', 'duplicate']))
            ->when($request->channel, fn ($q, $c) => $q->where('channel', $c))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('number', 'like', "%{$t}%")->orWhere('claimed_reference', 'like', "%{$t}%")
                ->orWhere('sender', 'like', "%{$t}%")->orWhereHas('customer', fn ($c) => $c->search($t))))
            ->orderBy('received_at')
            ->paginate(30)->withQueryString();

        $counts = [
            'open' => PaymentSubmission::whereIn('status', ['new', 'matched', 'info_requested'])->count(),
            'unmatched' => PaymentSubmission::where('status', 'new')->whereNull('customer_id')->count(),
            'pending_verification' => Payment::awaitingVerification()->count(),
            'flagged' => Payment::awaitingVerification()->where('duplicate_check', 'flagged')->count(),
        ];

        return view('payment-inbox.index', compact('items', 'tab', 'counts'));
    }

    public function create()
    {
        return view('payment-inbox.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['manual', 'email', 'whatsapp'])],
            'sender' => ['nullable', 'max:191'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'claimed_amount' => ['nullable', 'numeric', 'gt:0'],
            'claimed_date' => ['nullable', 'date', 'before_or_equal:today'],
            'claimed_method' => ['nullable', Rule::in(array_keys(Payment::METHODS))],
            'claimed_reference' => ['nullable', 'max:100'],
            'bank_id' => ['nullable', 'exists:banks,id'],
            'company_bank_account_id' => ['nullable', 'exists:company_bank_accounts,id'],
            'invoice_refs' => ['nullable', 'max:255'],
            'notes' => ['nullable', 'max:255'],
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => DocumentAttachment::rules(true),
        ], ['files.required' => 'Attach the payment slip.']);

        $submission = $this->inbox->receive($data, $request->file('files', []));
        $warnings = $this->inbox->duplicateWarnings($submission);

        return redirect()->route('payment-inbox.show', $submission)
            ->with($warnings ? 'error' : 'success', "Slip {$submission->number} saved.".($warnings ? ' Possible duplicate – see warnings.' : ''));
    }

    public function show(PaymentSubmission $submission)
    {
        $submission->load(['customer', 'attachments', 'assignee', 'creator', 'payment', 'bank', 'bankAccount.bank']);
        if ($submission->customer) {
            $this->authorizeDealer($submission->customer);
        }

        return view('payment-inbox.show', [
            's' => $submission,
            'warnings' => $this->inbox->duplicateWarnings($submission),
            'customers' => Customer::where('is_active', true)->visibleTo(request()->user())->orderBy('name')->get(),
            'staff' => User::where('is_active', true)->get()->filter(fn ($u) => $u->can('payments.record'))->pluck('name', 'id'),
            'previous' => $submission->customer?->payments()->latest('id')->limit(6)->get() ?? collect(),
            'acknowledgement' => PaymentInboxService::acknowledgement($submission),
        ] + $this->formData());
    }

    public function match(Request $request, PaymentSubmission $submission)
    {
        $data = $request->validate(['customer_id' => ['required', 'exists:customers,id']]);
        $customer = Customer::findOrFail($data['customer_id']);
        $this->authorizeDealer($customer);
        $this->inbox->match($submission, $customer, $request->user());

        return back()->with('success', "Matched to {$customer->name}.");
    }

    public function convert(Request $request, PaymentSubmission $submission)
    {
        $data = $request->validate(PaymentController::rules() + [
            'customer_id' => ['required', 'exists:customers,id'],
            'allocation_mode' => ['required', 'in:auto,manual,none'],
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $customer = Customer::findOrFail($data['customer_id']);
        $this->authorizeDealer($customer);

        $payment = $this->inbox->convert($submission, $data, $data['allocation_mode'] === 'manual' ? ($data['allocations'] ?? []) : [], $request->user());

        return redirect()->route('payments.show', $payment)->with('success', "Payment {$payment->number} created from {$submission->number} and sent for verification.");
    }

    public function action(Request $request, PaymentSubmission $submission, string $action)
    {
        $user = $request->user();
        match ($action) {
            'request-info' => $this->inbox->requestInformation($submission, $user, $request->validate(['note' => ['required', 'max:255']])['note']),
            'reject' => $this->inbox->reject($submission, $user, $request->validate(['note' => ['required', 'max:255']])['note']),
            'duplicate' => $this->inbox->reject($submission, $user, $request->validate(['note' => ['required', 'max:255']])['note'], true),
            'assign' => $this->inbox->assign($submission, $request->validate(['assigned_to' => ['nullable', 'exists:users,id']])['assigned_to'] ?? null),
            default => abort(404),
        };

        return back()->with('success', ['request-info' => 'Information requested.', 'reject' => 'Slip rejected.', 'duplicate' => 'Marked as duplicate.', 'assign' => 'Assignment updated.'][$action]);
    }

    private function formData(): array
    {
        return [
            'banks' => Bank::where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_name']),
            'accounts' => CompanyBankAccount::with('bank')->where('is_active', true)->where('receives_dealer_payments', true)->get(),
            'dealers' => Customer::where('is_active', true)->visibleTo(request()->user())->orderBy('name')->get(['id', 'name', 'code']),
        ];
    }
}
