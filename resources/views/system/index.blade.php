<x-layouts.app title="Scheduled tasks">
    <x-page-header title="Scheduled tasks & email" :subtitle="'Daily emails and housekeeping. Times are '.config('app.timezone').'.'" />

    @if ($schedulerRunning)
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            The scheduler is running (last activity {{ $lastTick->format('d/m/Y H:i') }}).
        </div>
    @else
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <strong>The scheduler is not running</strong>{{ $lastTick ? ' (last activity '.$lastTick->format('d/m/Y H:i').')' : '' }}, so the daily summary, reminders and housekeeping will not happen.
            Start it on the server:
            <ul class="mt-1 list-disc pl-5">
                <li>Linux: cron entry <code class="font-mono">* * * * * cd "{{ base_path() }}" &amp;&amp; php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code></li>
                <li>Windows: Task Scheduler, every 1 minute: <code class="font-mono">php "{{ base_path('artisan') }}" schedule:run</code></li>
                <li>Development: keep <code class="font-mono">php artisan schedule:work</code> running in a terminal</li>
            </ul>
        </div>
    @endif

    <div class="card max-w-3xl">
        <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="font-semibold">Outgoing email</h2>
        </div>
        <div class="card-body space-y-3 text-sm">
            @if ($mail['delivers'])
                <p>Mailer <strong>{{ $mail['mailer'] }}</strong>{{ $mail['host'] ? ' via '.$mail['host'] : '' }}, from <strong>{{ $mail['from'] }}</strong>.</p>
            @else
                <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">
                    Email is <strong>not being delivered</strong>: the mailer is <strong>{{ $mail['transport'] }}</strong>, which only writes messages to the log.
                    Enter your mail server in <a href="{{ route('system.mail') }}" class="font-medium underline">Email settings</a>, then send a test.
                </div>
            @endif
            @if ($failedEmails)
                <p class="text-red-700">{{ $failedEmails }} email(s) failed in the last 7 days. <a href="{{ route('system.emails', ['status' => 'failed']) }}" class="link">See the email log</a>.</p>
            @endif
            <form method="POST" action="{{ route('system.test-email') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <x-field label="Send a test email to" name="to" type="email" :value="auth()->user()->email" required class="min-w-56 flex-1" />
                <button class="btn btn-secondary">Send test</button>
            </form>
            <p><a href="{{ route('system.mail') }}" class="link">Email settings (SMTP)</a> · <a href="{{ route('system.emails') }}" class="link">Open the email log</a></p>
        </div>
    </div>

    <div class="card mt-6 overflow-x-auto">
        <div class="border-b border-slate-200 px-4 py-3"><h2 class="font-semibold">Scheduled tasks</h2></div>
        <table class="table">
            <thead><tr><th>Task</th><th>Next run</th><th>Last run</th><th>Result</th></tr></thead>
            <tbody>
            @foreach ($tasks as $t)
                <tr>
                    <td><div class="font-mono text-xs">{{ $t['command'] }}</div><div class="text-xs text-slate-500">{{ $t['description'] }}</div></td>
                    <td class="whitespace-nowrap">{{ $t['next']->timezone(config('app.timezone'))->format('D d/m H:i') }}</td>
                    <td class="whitespace-nowrap">{{ $t['last']?->started_at->format('d/m/Y H:i') ?? 'Never' }}</td>
                    <td>
                        @if ($t['last'])
                            <span class="badge {{ ['success' => 'bg-green-100 text-green-800', 'failed' => 'bg-red-100 text-red-800'][$t['last']->status] ?? 'bg-slate-100 text-slate-700' }}">{{ ucfirst($t['last']->status) }}</span>
                            <span class="text-xs text-slate-600">{{ \Illuminate\Support\Str::limit($t['last']->output, 160) }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="card mt-6 overflow-x-auto">
        <div class="border-b border-slate-200 px-4 py-3"><h2 class="font-semibold">Recent task history</h2></div>
        <table class="table">
            <thead><tr><th>Started</th><th>Task</th><th>Status</th><th class="num">Took</th><th>Output</th></tr></thead>
            <tbody>
            @forelse ($runs as $r)
                <tr>
                    <td class="whitespace-nowrap">{{ $r->started_at->format('d/m/Y H:i:s') }}</td>
                    <td class="font-mono text-xs">{{ $r->task }}</td>
                    <td><span class="badge {{ ['success' => 'bg-green-100 text-green-800', 'failed' => 'bg-red-100 text-red-800'][$r->status] ?? 'bg-slate-100 text-slate-700' }}">{{ ucfirst($r->status) }}</span></td>
                    <td class="num">{{ $r->finished_at ? (int) $r->started_at->diffInSeconds($r->finished_at).'s' : '' }}</td>
                    <td class="text-xs">{{ $r->output }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-slate-500">No tasks have run yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
