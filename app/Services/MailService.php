<?php

namespace App\Services;

use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * The only place the system sends email. Every attempt is written to email_logs with its real
 * outcome, and a mail failure never breaks the business action that triggered it.
 */
class MailService
{
    public function send(string $to, Mailable $mail, string $type, ?Model $related = null): EmailLog
    {
        $log = EmailLog::create([
            'type' => $type,
            'to' => $to,
            'subject' => Str::limit((string) ($mail->envelope()->subject ?? ''), 250),
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'status' => 'pending',
            'user_id' => auth()->id(),
        ]);

        try {
            Mail::to($to)->send($mail);
            $log->update(['status' => self::delivers() ? 'sent' : 'logged', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
            $log->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 1000)]);
        }

        return $log;
    }

    /** False when the mailer only writes to the log file / memory (nothing reaches an inbox). */
    public static function delivers(): bool
    {
        return ! in_array(self::transport(), ['log', 'array'], true);
    }

    public static function transport(): ?string
    {
        return config('mail.mailers.'.config('mail.default').'.transport');
    }

    public static function validAddress(?string $email): bool
    {
        return $email !== null && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Human-readable outcome for flash messages. */
    public static function outcome(EmailLog $log): array
    {
        return match ($log->status) {
            'sent' => ['success', "Email sent to {$log->to}."],
            'logged' => ['warning', "Email to {$log->to} was NOT delivered: the mailer is set to '".self::transport()."' (MAIL_MAILER in .env). It was written to the log only."],
            default => ['error', "Email to {$log->to} failed: ".Str::limit((string) $log->error, 200)],
        };
    }
}
