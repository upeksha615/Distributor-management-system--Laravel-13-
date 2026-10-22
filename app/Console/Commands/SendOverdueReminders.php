<?php

namespace App\Console\Commands;

use App\Mail\OverdueReminderMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\TaskRun;
use App\Services\MailService;
use Illuminate\Console\Command;

class SendOverdueReminders extends Command
{
    protected $signature = 'reminders:overdue {--force : Send even when switched off in Settings}';

    protected $description = 'Email each dealer with an email address a list of their overdue invoices';

    public function handle(MailService $mail): int
    {
        if (! config('distributor.notifications.overdue_reminders') && ! $this->option('force')) {
            $this->info('Overdue reminders are switched off in Settings.');

            return self::SUCCESS;
        }

        try {
            $result = TaskRun::track('reminders:overdue', function () use ($mail) {
                $byCustomer = Invoice::open()->where('due_date', '<', today())->orderBy('due_date')->get()->groupBy('customer_id');
                $sent = $failed = $skipped = 0;
                foreach ($byCustomer as $customerId => $invoices) {
                    $customer = Customer::find($customerId);
                    if (! $customer || ! $customer->is_active || ! MailService::validAddress($customer->email)) {
                        $skipped++;

                        continue;
                    }
                    $total = round($invoices->sum(fn ($i) => $i->balance()), 2);
                    $log = $mail->send(trim($customer->email), new OverdueReminderMail($customer, $invoices, $total, $customer->pendingPaymentsTotal()), 'overdue_reminder', $customer);
                    $log->status === 'failed' ? $failed++ : $sent++;
                }

                return "{$sent} sent, {$failed} failed, {$skipped} dealers without a valid email skipped.";
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info($result);

        return self::SUCCESS;
    }
}
