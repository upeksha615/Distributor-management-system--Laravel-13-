<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentStatusHistory;
use App\Models\User;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dealer payment workflow. Rules:
 *  - A recorded payment starts as pending verification (or draft); its invoice allocations are only proposals.
 *  - Money counts against invoices only when the payment is "settled" (verified, and for cheques cleared under the default policy).
 *  - The person who entered a payment cannot verify it (setting); amounts over the limit need payments.verify_above_limit.
 *  - Verified payments are corrected by reversal, never deleted.
 */
class PaymentService
{
    /**
     * Record a dealer payment. $allocations maps invoice_id => amount (proposed until verified); when empty and
     * $autoAllocate is true the amount is proposed against the oldest open invoices first.
     */
    public function record(Customer $customer, array $data, array $allocations = [], bool $autoAllocate = true): Payment
    {
        return DB::transaction(function () use ($customer, $data, $allocations, $autoAllocate) {
            $status = $data['status'] ?? 'pending_verification';
            $payment = Payment::create([
                'number' => DocumentNumber::next('payments', 'RCPT'),
                'customer_id' => $customer->id,
                'payment_date' => $data['payment_date'],
                'method' => $data['method'],
                'channel' => $data['channel'] ?? 'office',
                'amount' => $data['amount'],
                'reference' => $data['reference'] ?? null,
                'company_bank_account_id' => $data['company_bank_account_id'] ?? null,
                'cheque_number' => $data['cheque_number'] ?? null,
                'bank_id' => $data['bank_id'] ?? null,
                'bank_branch_id' => $data['bank_branch_id'] ?? null,
                'cheque_bank' => $data['cheque_bank'] ?? null,
                'cheque_date' => $data['cheque_date'] ?? null,
                'cheque_status' => $data['method'] === 'cheque' ? 'received' : null,
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'user_id' => auth()->id(),
                'rep_visit_id' => $data['rep_visit_id'] ?? null,
            ]);
            $this->log($payment, 'status', null, $status, 'Recorded via '.(Payment::CHANNELS[$payment->channel] ?? $payment->channel));
            if ($payment->method === 'cheque') {
                $this->log($payment, 'cheque_status', null, 'received', "Cheque {$payment->cheque_number} received, dated ".$payment->cheque_date?->format('d/m/Y'));
            }

            $this->flagDuplicates($payment);

            $allocations = array_filter($allocations, fn ($a) => (float) $a > 0);
            if ($allocations) {
                $this->allocate($payment, $allocations);
            } elseif ($autoAllocate) {
                $this->autoAllocate($payment);
            }

            AuditLog::record('payment.record', $payment, "Recorded {$payment->number}: ".money($payment->amount)." {$payment->methodLabel()} from {$customer->name} ({$payment->statusLabel()}).");

            return $payment;
        });
    }

    /**
     * Possible duplicates: same dealer + reference, same bank + cheque number, or same dealer + amount + date
     * (+ receiving account). Flagged for review; never discarded automatically.
     */
    public function findDuplicates(Payment $payment): \Illuminate\Support\Collection
    {
        return Payment::where('id', '!=', $payment->id)
            ->whereNotIn('status', ['rejected'])
            ->where(function ($q) use ($payment) {
                if ($payment->reference) {
                    $q->orWhere(fn ($w) => $w->where('customer_id', $payment->customer_id)->where('reference', $payment->reference));
                }
                if ($payment->method === 'cheque' && $payment->cheque_number) {
                    $q->orWhere(fn ($w) => $w->where('method', 'cheque')->where('cheque_number', $payment->cheque_number)
                        ->when($payment->bank_id, fn ($b) => $b->where('bank_id', $payment->bank_id), fn ($b) => $b->where('customer_id', $payment->customer_id)));
                }
                $q->orWhere(fn ($w) => $w->where('customer_id', $payment->customer_id)->where('amount', $payment->amount)
                    ->whereDate('payment_date', $payment->payment_date)->where('method', $payment->method)
                    ->when($payment->company_bank_account_id, fn ($b) => $b->where('company_bank_account_id', $payment->company_bank_account_id)));
            })
            ->orderBy('id')
            ->get();
    }

    public function flagDuplicates(Payment $payment): void
    {
        $dupes = $this->findDuplicates($payment);
        if ($dupes->isNotEmpty()) {
            $payment->update(['duplicate_of_id' => $dupes->first()->id, 'duplicate_check' => 'flagged']);
            $this->log($payment, 'status', $payment->status, $payment->status, 'Possible duplicate of '.$dupes->pluck('number')->join(', '));
        }
    }

    /** A reviewer confirms a flagged payment is NOT a duplicate. */
    public function clearDuplicate(Payment $payment, User $user, string $note): void
    {
        $payment->update(['duplicate_check' => 'cleared']);
        $this->log($payment, 'status', $payment->status, $payment->status, 'Duplicate warning reviewed: not a duplicate. '.$note, $user);
        AuditLog::record('payment.duplicate_cleared', $payment, "{$payment->number}: duplicate warning cleared by {$user->name}. {$note}");
    }

    /** Propose / apply part of a payment to specific invoices. Applies to the books once the payment is settled. */
    public function allocate(Payment $payment, array $allocations): void
    {
        if (in_array($payment->status, ['rejected', 'reversed'], true)) {
            throw ValidationException::withMessages(['allocations' => 'A rejected or reversed payment cannot be allocated.']);
        }

        DB::transaction(function () use ($payment, $allocations) {
            $available = $payment->unallocatedAmount();
            $requested = round(array_sum(array_map('floatval', $allocations)), 2);

            if ($requested > $available + 0.001) {
                throw ValidationException::withMessages([
                    'allocations' => 'Allocated total '.number_format($requested, 2).' is more than the available '.number_format($available, 2).'.',
                ]);
            }

            foreach ($allocations as $invoiceId => $amount) {
                $amount = round((float) $amount, 2);
                if ($amount <= 0) {
                    continue;
                }

                $invoice = Invoice::lockForUpdate()->findOrFail($invoiceId);
                if ($invoice->customer_id !== $payment->customer_id || ! in_array($invoice->status, ['unpaid', 'partial'])) {
                    throw ValidationException::withMessages(['allocations' => "Invoice {$invoice->number} cannot take this payment."]);
                }
                if ($amount > $invoice->balance() + 0.001) {
                    throw ValidationException::withMessages([
                        "allocations.{$invoiceId}" => "Invoice {$invoice->number} only has ".number_format($invoice->balance(), 2).' outstanding.',
                    ]);
                }

                $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $amount]);
                $invoice->refreshPaymentStatus();
            }
        });
    }

    /** Replace all allocations of a payment (before verification, or an authorised reallocation after). */
    public function reallocate(Payment $payment, array $allocations, User $user, string $reason): void
    {
        DB::transaction(function () use ($payment, $allocations, $user, $reason) {
            $before = $payment->allocations()->with('invoice')->get();
            $payment->allocations()->delete();
            foreach ($before as $a) {
                $a->invoice->refreshPaymentStatus();
            }
            $this->allocate($payment->fresh(), array_filter($allocations, fn ($a) => (float) $a > 0));

            $old = $before->map(fn ($a) => $a->invoice->number.' '.number_format((float) $a->amount, 2))->join(', ') ?: 'none';
            $new = $payment->allocations()->with('invoice')->get()->map(fn ($a) => $a->invoice->number.' '.number_format((float) $a->amount, 2))->join(', ') ?: 'none';
            $this->log($payment, 'status', $payment->status, $payment->status, "Reallocated. Was: {$old}. Now: {$new}. {$reason}", $user);
            AuditLog::record('payment.reallocate', $payment, "{$payment->number} reallocated by {$user->name}. Was: {$old}. Now: {$new}. Reason: {$reason}");
        });
    }

    public function autoAllocate(Payment $payment): void
    {
        $remaining = $payment->unallocatedAmount();
        $allocations = [];

        $invoices = Invoice::where('customer_id', $payment->customer_id)->open()->orderBy('due_date')->orderBy('id')->get();

        foreach ($invoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }
            $amount = min($remaining, $invoice->balance());
            if ($amount > 0) {
                $allocations[$invoice->id] = $amount;
                $remaining = round($remaining - $amount, 2);
            }
        }

        if ($allocations) {
            $this->allocate($payment, $allocations);
        }
    }

    /** Office admin hands a draft / submitted payment to the accountants. */
    public function submitForVerification(Payment $payment, User $user): void
    {
        $this->assertStatus($payment, ['draft', 'submitted', 'under_review']);
        $this->transition($payment, 'pending_verification', $user, 'Submitted for verification.');
    }

    /** Accountant asks for more information (missing slip, unclear reference...). */
    public function requestInformation(Payment $payment, User $user, string $note): void
    {
        $this->assertStatus($payment, ['submitted', 'pending_verification', 'under_review']);
        $this->transition($payment, 'under_review', $user, 'More information requested: '.$note);
    }

    /**
     * Verify the payment against the actual bank record. For non-cheque payments the money now settles the
     * allocated invoices; for cheques it settles on clearance (default policy).
     */
    public function verify(Payment $payment, User $user, ?string $note = null): void
    {
        $this->assertStatus($payment, ['submitted', 'pending_verification', 'under_review']);
        abort_unless($user->can('payments.verify'), 403, 'You are not allowed to verify payments.');

        if (config('distributor.payment_segregation') && $payment->user_id === $user->id) {
            throw ValidationException::withMessages(['verify' => 'You entered this payment, so another authorised person must verify it.']);
        }
        $limit = (float) config('distributor.payment_verify_limit');
        if ($limit > 0 && (float) $payment->amount > $limit && ! $user->can('payments.verify_above_limit')) {
            throw ValidationException::withMessages(['verify' => 'Payments above '.money($limit).' must be verified by a user with the "verify above limit" permission.']);
        }
        if ($payment->duplicate_check === 'flagged') {
            throw ValidationException::withMessages(['verify' => 'This payment is flagged as a possible duplicate. Review the warning first.']);
        }

        DB::transaction(function () use ($payment, $user, $note) {
            // Allocations proposed earlier must still fit the invoices' current balances.
            foreach ($payment->allocations()->with('invoice')->get() as $a) {
                if ((float) $a->amount > $a->invoice->balance() + 0.001) {
                    throw ValidationException::withMessages(['verify' => "Invoice {$a->invoice->number} now has only ".money($a->invoice->balance()).' outstanding. Reallocate before verifying.']);
                }
            }

            $payment->forceFill(['verified_by' => $user->id, 'verified_at' => now()])->save();
            $this->transition($payment, 'verified', $user, $note ?: 'Verified against bank records.');
            $this->refreshInvoices($payment);
        });
        app(DealerNotifier::class)->payment($payment, 'verified');
    }

    public function reject(Payment $payment, User $user, string $reason, bool $duplicate = false): void
    {
        $this->assertStatus($payment, ['draft', 'submitted', 'pending_verification', 'under_review']);
        abort_unless($user->can('payments.verify') || $duplicate && $user->can('payments.record'), 403);

        $wasDraft = $payment->status === 'draft';
        DB::transaction(function () use ($payment, $user, $reason, $duplicate) {
            $payment->update(['rejection_reason' => ($duplicate ? 'Duplicate. ' : '').$reason, 'cheque_status' => $payment->method === 'cheque' ? 'cancelled' : null]);
            $payment->allocations()->delete();
            $this->transition($payment, 'rejected', $user, ($duplicate ? 'Rejected as duplicate: ' : 'Rejected: ').$reason);
        });
        if (! $duplicate && ! $wasDraft) {
            app(DealerNotifier::class)->payment($payment, 'rejected');
        }
    }

    /** Undo a verified payment (wrong dealer, bank reversal...). Allocations stay as history but stop counting. */
    public function reverse(Payment $payment, User $user, string $reason): void
    {
        $this->assertStatus($payment, ['verified']);
        abort_unless($user->can('payments.reverse'), 403, 'You are not allowed to reverse payments.');

        DB::transaction(function () use ($payment, $user, $reason) {
            $payment->forceFill(['reversed_by' => $user->id, 'reversed_at' => now(), 'reversal_reason' => $reason])->save();
            $this->transition($payment, 'reversed', $user, 'Reversed: '.$reason);
            $this->refreshInvoices($payment);
        });
    }

    // ---------------- Cheques ----------------

    public function depositCheque(Payment $payment, User $user, string $date, string $bank, ?int $bankAccountId = null, ?string $reference = null): void
    {
        $this->assertCheque($payment, ['received']);
        if (! in_array($payment->status, ['pending_verification', 'under_review', 'submitted', 'verified'], true)) {
            throw ValidationException::withMessages(['deposit' => "{$payment->number} is {$payment->statusLabel()} and cannot be deposited."]);
        }
        if ($payment->cheque_date && $payment->cheque_date->toDateString() > $date) {
            throw ValidationException::withMessages(['deposit' => "Cheque {$payment->cheque_number} is dated {$payment->cheque_date->format('d/m/Y')}, after the deposit date."]);
        }

        DB::transaction(function () use ($payment, $user, $date, $bank, $bankAccountId, $reference) {
            $payment->update(['deposited_date' => $date, 'deposit_bank' => $bank, 'deposit_reference' => $reference,
                'company_bank_account_id' => $bankAccountId ?? $payment->company_bank_account_id]);
            $this->chequeTransition($payment, 'deposited', $user, "Deposited to {$bank}".($reference ? " (ref {$reference})" : '').' on '.date('d/m/Y', strtotime($date)));
        });
    }

    /** The bank has cleared the cheque. The payment must have been verified first. */
    public function clearCheque(Payment $payment, User $user, string $date): void
    {
        $this->assertCheque($payment, ['deposited']);
        abort_unless($user->can('cheques.clear'), 403);
        if ($payment->status !== 'verified') {
            throw ValidationException::withMessages(['clear' => "Verify {$payment->number} before recording its clearance."]);
        }
        if ($payment->deposited_date && $date < $payment->deposited_date->toDateString()) {
            throw ValidationException::withMessages(['clear' => 'The cleared date cannot be before the deposit date.']);
        }

        DB::transaction(function () use ($payment, $user, $date) {
            $payment->update(['cleared_date' => $date]);
            $this->chequeTransition($payment, 'cleared', $user, 'Cleared by the bank on '.date('d/m/Y', strtotime($date)));
            $this->refreshInvoices($payment);
        });
    }

    /** The cheque bounced: record reason and bank charges, reverse the receipt and reopen the invoices. */
    public function returnCheque(Payment $payment, string $date, ?string $reason = null, float $bankCharges = 0, ?User $user = null): void
    {
        $user ??= auth()->user();
        if ($payment->cheque_status === 'returned') {
            return;
        }
        $this->assertCheque($payment, ['received', 'deposited', 'cleared']);

        DB::transaction(function () use ($payment, $date, $reason, $bankCharges, $user) {
            $payment->update(['returned_date' => $date, 'return_reason' => $reason, 'bank_charges' => $bankCharges]);
            $this->chequeTransition($payment, 'returned', $user, 'Returned on '.date('d/m/Y', strtotime($date)).': '.($reason ?: 'no reason given')
                .($bankCharges > 0 ? '. Bank charges '.money($bankCharges) : ''));

            if (in_array($payment->status, ['verified'], true)) {
                $payment->forceFill(['reversed_by' => $user?->id, 'reversed_at' => now(), 'reversal_reason' => 'Cheque returned: '.$reason])->save();
                $this->transition($payment, 'reversed', $user, 'Receipt reversed because the cheque was returned.');
            } elseif ($payment->isOpen()) {
                $this->transition($payment, 'rejected', $user, 'Cheque returned before verification.');
            }
            $this->refreshInvoices($payment);

            AuditLog::record('payment.return', $payment, "Cheque {$payment->cheque_number} ({$payment->number}, ".money($payment->amount).", {$payment->customer->name}) returned. "
                .($reason ?? '').($bankCharges > 0 ? ' Bank charges '.money($bankCharges).'.' : ''));
        });
        app(DealerNotifier::class)->payment($payment, 'returned');
    }

    // ---------------- helpers ----------------

    private function refreshInvoices(Payment $payment): void
    {
        foreach ($payment->allocations()->with('invoice')->get() as $allocation) {
            $allocation->invoice->refreshPaymentStatus();
        }
    }

    private function transition(Payment $payment, string $to, ?User $user, string $note): void
    {
        $from = $payment->status;
        $payment->update(['status' => $to]);
        $this->log($payment, 'status', $from, $to, $note, $user);
        AuditLog::record('payment.'.$to, $payment, "{$payment->number} ({$payment->customer->name}, ".money($payment->amount)."): {$from} → {$to}. {$note}");
    }

    private function chequeTransition(Payment $payment, string $to, ?User $user, string $note): void
    {
        $from = $payment->cheque_status;
        $payment->update(['cheque_status' => $to]);
        $this->log($payment, 'cheque_status', $from, $to, $note, $user);
        AuditLog::record('cheque.'.$to, $payment, "Cheque {$payment->cheque_number} ({$payment->number}, ".money($payment->amount)."): {$note}");
    }

    private function log(Payment $payment, string $field, ?string $from, string $to, ?string $note, ?User $user = null): void
    {
        PaymentStatusHistory::create(['payment_id' => $payment->id, 'field' => $field, 'from' => $from, 'to' => $to,
            'note' => $note ? mb_substr($note, 0, 255) : null, 'user_id' => ($user ?? auth()->user())?->id]);
    }

    private function assertStatus(Payment $payment, array $allowed): void
    {
        if (! in_array($payment->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "{$payment->number} is {$payment->statusLabel()}; this action is not possible."]);
        }
    }

    private function assertCheque(Payment $payment, array $allowed): void
    {
        if ($payment->method !== 'cheque' || ! in_array($payment->cheque_status ?? 'received', $allowed, true)) {
            throw ValidationException::withMessages(['cheque' => "{$payment->number}: cheque is ".($payment->cheque_status ?? 'n/a').'; this action is not possible.']);
        }
    }
}
