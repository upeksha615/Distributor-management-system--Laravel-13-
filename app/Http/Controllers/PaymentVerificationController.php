<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

/** Accountant: queue of dealer payments waiting for verification against the bank. */
class PaymentVerificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $limit = (float) config('distributor.payment_verify_limit');

        $payments = $this->dealerScope(Payment::with(['customer', 'user', 'bankAccount.bank', 'bank']))->withCount('attachments')
            ->awaitingVerification()
            ->when($request->method, fn ($q, $m) => $q->where('method', $m))
            ->when($request->account, fn ($q, $a) => $q->where('company_bank_account_id', $a))
            ->when($request->flag === 'duplicates', fn ($q) => $q->where('duplicate_check', 'flagged'))
            ->when($request->flag === 'mine', fn ($q) => $q->where('user_id', '!=', $user->id))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('number', 'like', "%{$t}%")->orWhere('reference', 'like', "%{$t}%")
                ->orWhere('cheque_number', 'like', "%{$t}%")->orWhereHas('customer', fn ($c) => $c->search($t))))
            ->orderBy('payment_date')->orderBy('id')
            ->paginate(40)->withQueryString();

        $base = Payment::awaitingVerification();

        return view('payment-verification.index', [
            'payments' => $payments,
            'limit' => $limit,
            'segregation' => (bool) config('distributor.payment_segregation'),
            'summary' => [
                'count' => (clone $base)->count(),
                'amount' => (float) (clone $base)->sum('amount'),
                'under_review' => (clone $base)->where('status', 'under_review')->count(),
                'flagged' => (clone $base)->where('duplicate_check', 'flagged')->count(),
                'drafts' => (clone $base)->where('status', 'draft')->count(),
            ],
            'accounts' => \App\Models\CompanyBankAccount::with('bank')->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()]),
        ]);
    }
}
