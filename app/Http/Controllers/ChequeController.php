<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Exporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Register of customer cheques (payments with method = cheque): deposit, clear, return. */
class ChequeController extends Controller
{
    public const TABS = [
        'in_hand' => 'In hand',
        'due' => 'Due to deposit',
        'unverified' => 'Not yet verified',
        'deposited' => 'Awaiting clearance',
        'cleared' => 'Cleared',
        'returned' => 'Returned',
        'all' => 'All',
    ];

    public function __construct(private PaymentService $payments) {}

    public function index(Request $request)
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'in_hand';

        $cheques = $this->scope($this->dealerScope(Payment::with(['customer', 'bank', 'branch'])), $tab)
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w->where('number', 'like', "%{$term}%")
                ->orWhere('cheque_number', 'like', "%{$term}%")
                ->orWhere('cheque_bank', 'like', "%{$term}%")
                ->orWhere('deposit_bank', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->search($term))))
            ->when($request->from, fn ($q, $d) => $q->where('cheque_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('cheque_date', '<=', $d))
            ->orderBy('cheque_date')->orderBy('id');

        if ($format = $this->exportFormat($request)) {
            $rows = $cheques->get();

            return Exporter::download($format, 'cheques-'.$tab, 'Cheque register - '.self::TABS[$tab], [
                'Receipt' => 'text', 'Received' => 'text', 'Customer' => 'text', 'Cheque no' => 'text', 'Bank' => 'text',
                'Cheque date' => 'text', 'Days after cheque date' => 'number', 'Status' => 'text', 'Deposited' => 'text',
                'Deposit bank' => 'text', 'Cleared' => 'text', 'Returned' => 'text', 'Amount' => 'money',
            ], $rows->map(fn ($p) => [
                $p->number, $p->payment_date->format('d/m/Y'), $p->customer->name, $p->cheque_number, trim(($p->bank?->name ?? $p->cheque_bank).' '.$p->branch?->name),
                $p->cheque_date?->format('d/m/Y'), self::daysAfter($p), ucfirst($p->chequeStatus()).' / '.$p->statusLabel(), $p->deposited_date?->format('d/m/Y'),
                $p->deposit_bank, $p->cleared_date?->format('d/m/Y'), $p->returned_date?->format('d/m/Y'), $p->amount,
            ]), $this->filterSummary($request), ['Total ('.$rows->count().' cheques)', null, null, null, null, null, null, null, null, null, null, null, $rows->sum('amount')]);
        }

        $total = (float) (clone $cheques)->reorder()->sum('amount');
        $count = (clone $cheques)->reorder()->count();
        $cheques = $cheques->paginate(50)->withQueryString();

        $summary = [
            'in_hand' => $this->scope(Payment::query(), 'in_hand')->sum('amount'),
            'in_hand_count' => $this->scope(Payment::query(), 'in_hand')->count(),
            'due' => $this->scope(Payment::query(), 'due')->sum('amount'),
            'due_count' => $this->scope(Payment::query(), 'due')->count(),
            'deposited' => $this->scope(Payment::query(), 'deposited')->sum('amount'),
            'deposited_count' => $this->scope(Payment::query(), 'deposited')->count(),
        ];

        $depositBanks = \App\Models\CompanyBankAccount::with('bank')->where('is_active', true)->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()]);

        return view('cheques.index', compact('cheques', 'tab', 'total', 'count', 'summary', 'depositBanks'));
    }

    /** Deposit one or more in-hand cheques to a bank account. */
    public function deposit(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'deposited_date' => ['required', 'date', 'before_or_equal:today'],
            'company_bank_account_id' => ['required', 'exists:company_bank_accounts,id'],
            'deposit_reference' => ['nullable', 'max:100'],
        ], ['ids.required' => 'Select at least one cheque to deposit.']);

        $account = \App\Models\CompanyBankAccount::with('bank')->findOrFail($data['company_bank_account_id']);
        $cheques = $this->scope(Payment::query(), 'in_hand')->whereIn('id', $data['ids'])->get();
        if ($cheques->count() !== count(array_unique($data['ids']))) {
            throw ValidationException::withMessages(['ids' => 'Some selected cheques are no longer in hand (already deposited or returned). Refresh and try again.']);
        }

        DB::transaction(function () use ($cheques, $data, $account, $request) {
            foreach ($cheques as $p) {
                $this->payments->depositCheque($p, $request->user(), $data['deposited_date'], $account->label(), $account->id, $data['deposit_reference'] ?? null);
            }
        });

        return back()->with('success', $cheques->count().' cheque(s) deposited to '.$account->label().', total '.money($cheques->sum('amount')).'. They count as paid only after the bank clears them.');
    }

    public function clear(Request $request, Payment $payment)
    {
        $data = $request->validate(['cleared_date' => ['required', 'date', 'before_or_equal:today']]);
        $this->payments->clearCheque($payment, $request->user(), $data['cleared_date']);

        return back()->with('success', "Cheque {$payment->cheque_number} cleared. Its invoices are updated.");
    }

    public function returnCheque(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'returned_date' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', 'max:255'],
            'bank_charges' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->payments->returnCheque($payment, $data['returned_date'], $data['reason'], (float) ($data['bank_charges'] ?? 0), $request->user());

        return back()->with('success', "Cheque {$payment->cheque_number} marked as returned. The receipt is reversed and the invoices are open again.");
    }
    /** Days since the cheque date (negative = days still to go). */
    public static function daysAfter(Payment $p): ?int
    {
        return $p->cheque_date ? (int) $p->cheque_date->diffInDays(today(), false) : null;
    }

    private function scope(Builder $query, string $tab): Builder
    {
        $query->where('method', 'cheque');

        return match ($tab) {
            'in_hand' => $query->where('cheque_status', 'received')->whereNotIn('status', ['rejected', 'reversed']),
            'due' => $query->where('cheque_status', 'received')->whereNotIn('status', ['rejected', 'reversed'])->whereDate('cheque_date', '<=', today()),
            'unverified' => $query->whereIn('status', Payment::OPEN_STATUSES)->whereNotIn('cheque_status', ['returned', 'cancelled']),
            'deposited' => $query->where('cheque_status', 'deposited'),
            'cleared' => $query->where('cheque_status', 'cleared'),
            'returned' => $query->where('cheque_status', 'returned'),
            default => $query,
        };
    }
}
