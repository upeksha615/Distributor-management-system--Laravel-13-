<?php

namespace App\Http\Controllers;

use App\Mail\TestMail;
use App\Models\EmailLog;
use App\Models\TaskRun;
use App\Services\MailService;
use App\Support\ScheduledTasks;
use Illuminate\Http\Request;

/** Scheduled tasks and the email log (Super Admin: system.manage). */
class SystemController extends Controller
{
    public function index()
    {
        $tasks = collect(ScheduledTasks::overview())->map(fn ($t) => $t + ['last' => TaskRun::latestFor($t['command'])]);
        $tick = ScheduledTasks::lastTick();

        return view('system.index', [
            'tasks' => $tasks,
            'runs' => TaskRun::latest('started_at')->latest('id')->limit(15)->get(),
            'lastTick' => $tick,
            'schedulerRunning' => $tick && $tick->gt(now()->subMinutes(5)),
            'mail' => [
                'mailer' => config('mail.default'),
                'transport' => MailService::transport(),
                'delivers' => MailService::delivers(),
                'host' => config('mail.mailers.'.config('mail.default').'.host'),
                'from' => config('mail.from.address'),
            ],
            'failedEmails' => EmailLog::where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count(),
        ]);
    }

    public function testEmail(Request $request, MailService $mail)
    {
        $data = $request->validate(['to' => ['required', 'email']]);
        $log = $mail->send($data['to'], new TestMail, 'test');
        [$type, $message] = MailService::outcome($log);

        return back()->with($type, $message);
    }

    public function emails(Request $request)
    {
        $logs = EmailLog::with('user')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('to', 'like', "%{$t}%")->orWhere('subject', 'like', "%{$t}%")))
            ->latest('id')->paginate(50)->withQueryString();

        return view('system.emails', compact('logs'));
    }
}
