<?php

namespace App\Services;

use App\Mail\PaymentNoticeMail;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Emails dealers about their payments. Sent only after the database change has committed,
 * only when enabled in Settings, and only when the dealer has a valid email address.
 */
class DealerNotifier
{
    public function __construct(private MailService $mail) {}

    public function payment(Payment $payment, string $kind): void
    {
        if (! config('distributor.notifications.dealer_payment_notices')) {
            return;
        }

        DB::afterCommit(function () use ($payment, $kind) {
            try {
                $payment->refresh()->loadMissing('customer', 'allocations.invoice');
                $email = $payment->customer?->email;
                if (! MailService::validAddress($email)) {
                    return;
                }
                $this->mail->send(trim($email), new PaymentNoticeMail($payment, $kind), $kind === 'returned' ? 'cheque_returned' : 'payment_'.$kind, $payment);
            } catch (\Throwable $e) {
                report($e); // never let a notification undo or block the payment action
            }
        });
    }
}
