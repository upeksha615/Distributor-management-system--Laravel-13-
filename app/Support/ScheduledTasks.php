<?php

namespace App\Support;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;

/**
 * The automatic tasks. Registered in routes/console.php and listed on Setup → Scheduled tasks.
 * They run only while the scheduler runs: cron `* * * * * php artisan schedule:run` on Linux,
 * Windows Task Scheduler every minute, or `php artisan schedule:work` during development.
 */
class ScheduledTasks
{
    /** command => description */
    public const TASKS = [
        'summary:daily' => 'Email the daily summary to managers',
        'reminders:overdue' => 'Email dealers their overdue invoices (when enabled in Settings)',
        'visits:close-stale' => 'Close rep visits left open from previous days',
        'auth:clear-resets' => 'Delete expired password reset links',
    ];

    public static function register(Schedule $schedule): void
    {
        $schedule->command('summary:daily')->dailyAt(config('distributor.notifications.daily_summary_time', '07:30'));
        $schedule->command('reminders:overdue')->weeklyOn(1, config('distributor.notifications.overdue_reminder_time', '08:30'));
        $schedule->command('visits:close-stale')->dailyAt('00:15');
        $schedule->command('auth:clear-resets')->dailyAt('03:00');

        // Heartbeat so the Scheduled tasks page can tell whether the scheduler is actually running.
        $schedule->call(fn () => Cache::forever('scheduler:last_tick', now()->toIso8601String()))->everyMinute()->name('scheduler-heartbeat');
    }

    /** The tasks with their timing, for display. */
    public static function overview(): array
    {
        $schedule = new Schedule(config('app.timezone'));
        self::register($schedule);

        return collect($schedule->events())
            ->filter(fn ($e) => $e->command)
            ->map(function ($event) {
                $command = collect(self::TASKS)->keys()->first(fn ($c) => str_contains($event->command, $c));

                return [
                    'command' => $command,
                    'description' => self::TASKS[$command] ?? '',
                    'expression' => $event->expression,
                    'next' => $event->nextRunDate(),
                ];
            })->values()->all();
    }

    public static function lastTick(): ?\Carbon\Carbon
    {
        $tick = Cache::get('scheduler:last_tick');

        return $tick ? \Carbon\Carbon::parse($tick) : null;
    }
}
