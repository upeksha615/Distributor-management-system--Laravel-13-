<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DocumentAttachment;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Payment slip inbox: incoming slips (manual, email, WhatsApp, portal) → matched to a dealer → turned into payments. */
class PaymentInboxService
{
    public function __construct(private PaymentService $payments) {}

    /** Record an incoming slip. $files: UploadedFile[] */
    public function receive(array $data, array $files = []): PaymentSubmission
    {
        return DB::transaction(function () use ($data, $files) {
            $submission = PaymentSubmission::create([
                'number' => DocumentNumber::next('payment_submissions', 'SLIP'),
                'channel' => $data['channel'] ?? 'manual',
                'status' => ! empty($data['customer_id']) ? 'matched' : 'new',
                'customer_id' => $data['customer_id'] ?? null,
                'sender' => $data['sender'] ?? null,
                'subject' => $data['subject'] ?? null,
                'message' => $data['message'] ?? null,
                'external_id' => $data['external_id'] ?? null,
                'claimed_amount' => $data['claimed_amount'] ?? null,
                'claimed_date' => $data['claimed_date'] ?? null,
                'claimed_reference' => $data['claimed_reference'] ?? null,
                'claimed_method' => $data['claimed_method'] ?? null,
                'bank_id' => $data['bank_id'] ?? null,
                'company_bank_account_id' => $data['company_bank_account_id'] ?? null,
                'invoice_refs' => $data['invoice_refs'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
                'created_by' => auth()->id(),
            ]);
            foreach ($files as $file) {
                DocumentAttachment::store($submission, $file, 'slip');
            }
            AuditLog::record('inbox.receive', $submission, "Payment slip {$submission->number} received via {$submission->channel}"
                .($submission->customer_id ? " for {$submission->customer->name}" : '').($submission->claimed_amount ? ', claimed '.money($submission->claimed_amount) : '').'.');

            return $submission;
        });
    }

    /** Duplicate warnings: same reference, same file, or same dealer + amount + date already in payments or other slips. */
    public function duplicateWarnings(PaymentSubmission $s): array
    {
        $warnings = [];
        if ($s->claimed_reference) {
            foreach (Payment::where(fn ($q) => $q->where('reference', $s->claimed_reference)->orWhere('cheque_number', $s->claimed_reference))
                ->whereNotIn('status', ['rejected'])->where('id', '!=', $s->payment_id ?? 0)->get() as $p) {
                $warnings[] = "Reference {$s->claimed_reference} already used on payment {$p->number} ({$p->statusLabel()}).";
            }
            foreach (PaymentSubmission::where('id', '!=', $s->id)->where('claimed_reference', $s->claimed_reference)->whereNotIn('status', ['rejected', 'duplicate'])->get() as $o) {
                $warnings[] = "Reference {$s->claimed_reference} also on slip {$o->number}.";
            }
        }
        $hashes = $s->attachments()->pluck('sha256');
        if ($hashes->isNotEmpty()) {
            $same = DocumentAttachment::whereIn('sha256', $hashes)
                ->where(fn ($q) => $q->where('attachable_type', '!=', $s->getMorphClass())->orWhere('attachable_id', '!=', $s->id))
                ->get()->reject(fn ($a) => $a->attachable_type === (new Payment)->getMorphClass() && $a->attachable_id === $s->payment_id);
            foreach ($same as $a) {
                $owner = $a->attachable;
                $warnings[] = 'The same file was already uploaded on '.($owner->number ?? 'another record').'.';
            }
        }
        if ($s->customer_id && $s->claimed_amount && $s->claimed_date) {
            foreach (Payment::where('customer_id', $s->customer_id)->where('amount', $s->claimed_amount)->whereDate('payment_date', $s->claimed_date)
                ->whereNotIn('status', ['rejected'])->where('id', '!=', $s->payment_id ?? 0)->get() as $p) {
                $warnings[] = "Same dealer, amount and date as payment {$p->number}.";
            }
        }

        return array_values(array_unique($warnings));
    }

    public function match(PaymentSubmission $s, Customer $customer, User $user): void
    {
        $this->assertOpen($s);
        $s->update(['customer_id' => $customer->id, 'status' => 'matched']);
        AuditLog::record('inbox.match', $s, "{$s->number} matched to {$customer->name} by {$user->name}.");
    }

    /** Turn the slip into a payment waiting for verification. */
    public function convert(PaymentSubmission $s, array $data, array $allocations, User $user): Payment
    {
        $this->assertOpen($s);

        return DB::transaction(function () use ($s, $data, $allocations, $user) {
            $customer = Customer::findOrFail($data['customer_id']);
            $channel = ['manual' => 'inbox', 'email' => 'email', 'whatsapp' => 'whatsapp', 'portal' => 'portal'][$s->channel] ?? 'inbox';
            $payment = $this->payments->record($customer, $data + ['channel' => $channel, 'status' => 'pending_verification'],
                $allocations, empty($allocations) && ($data['allocation_mode'] ?? 'auto') === 'auto');

            foreach ($s->attachments as $attachment) {
                $attachment->copyTo($payment);
            }
            $this->payments->flagDuplicates($payment->fresh());

            $s->update(['customer_id' => $customer->id, 'status' => 'converted', 'payment_id' => $payment->id]);
            AuditLog::record('inbox.convert', $s, "{$s->number} turned into payment {$payment->number} by {$user->name}.");

            return $payment;
        });
    }

    public function requestInformation(PaymentSubmission $s, User $user, string $note): void
    {
        $this->assertOpen($s);
        $s->update(['status' => 'info_requested', 'notes' => trim(($s->notes ? $s->notes."\n" : '').now()->format('d/m H:i')." {$user->name}: {$note}")]);
        AuditLog::record('inbox.info_requested', $s, "{$s->number}: information requested. {$note}");
    }

    public function reject(PaymentSubmission $s, User $user, string $reason, bool $duplicate = false): void
    {
        $this->assertOpen($s);
        $s->update(['status' => $duplicate ? 'duplicate' : 'rejected', 'rejection_reason' => $reason]);
        AuditLog::record($duplicate ? 'inbox.duplicate' : 'inbox.reject', $s, "{$s->number} ".($duplicate ? 'marked duplicate' : 'rejected')." by {$user->name}: {$reason}");
    }

    public function assign(PaymentSubmission $s, ?int $userId): void
    {
        $s->update(['assigned_to' => $userId]);
    }

    private function assertOpen(PaymentSubmission $s): void
    {
        if (! $s->isOpen()) {
            throw ValidationException::withMessages(['status' => "{$s->number} is already ".strtolower(PaymentSubmission::STATUSES[$s->status]).'.']);
        }
    }

    /** Message texts. Sent only when email / WhatsApp are configured; otherwise staff copy them. */
    public static function acknowledgement(PaymentSubmission $s): string
    {
        return "Your payment slip has been received. Reference: {$s->number}. The payment is pending verification. This is not a confirmation that the funds have cleared.";
    }

    public static function approvalMessage(Payment $p): string
    {
        return 'Your payment of LKR '.number_format((float) $p->amount, 2)." has been verified and recorded. Receipt No: {$p->number}.";
    }
}
