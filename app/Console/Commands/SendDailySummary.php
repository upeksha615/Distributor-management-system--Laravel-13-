<?php

namespace App\Console\Commands;

use App\Mail\DailySummaryMail;
use App\Models\TaskRun;
use App\Models\User;
use App\Services\MailService;
use App\Support\DailySummary;
use Illuminate\Console\Command;

class SendDailySummary extends Command
{
    protected $signature = 'summary:daily';

    protected $description = 'Email the daily summary to users with the "Receive the daily summary email" permission';

    public function handle(MailService $mail): int
    {
        try {
            $result = TaskRun::track('summary:daily', function () use ($mail) {
                $recipients = User::where('is_active', true)->get()
                    ->filter(fn (User $u) => ! $u->isDealer() && $u->hasPermission('notifications.daily_summary') && MailService::validAddress($u->email));
                if ($recipients->isEmpty()) {
                    return 'No recipients (give a role the "Receive the daily summary email" permission).';
                }
                $summary = DailySummary::build();
                $logs = $recipients->map(fn (User $u) => $mail->send($u->email, new DailySummaryMail($summary), 'daily_summary'));
                $failed = $logs->where('status', 'failed');
                if ($failed->count() === $logs->count()) {
                    throw new \RuntimeException('Sending failed: '.$failed->first()->error);
                }

                return $logs->countBy('status')->map(fn ($n, $s) => "$n $s")->join(', ').' ('.$recipients->pluck('email')->join(', ').')';
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info($result);

        return self::SUCCESS;
    }
}
