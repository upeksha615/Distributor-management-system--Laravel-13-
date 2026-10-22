<x-layouts.app title="Email log">
    <x-page-header title="Email log" subtitle="Every email the system tried to send and what actually happened.">
        <a href="{{ route('system.index') }}" class="btn btn-secondary">Scheduled tasks</a>
    </x-page-header>

    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-48 flex-1"><label class="label">Search</label><input type="search" name="q" value="{{ request('q') }}" placeholder="Recipient or subject" class="input"></div>
        <x-select label="Type" name="type" :options="\App\Models\EmailLog::TYPES" :value="request('type')" placeholder="All types" class="w-48" />
        <x-select label="Status" name="status" :options="\App\Models\EmailLog::STATUSES" :value="request('status')" placeholder="All" class="w-56" />
        <button class="btn btn-secondary">Filter</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Time</th><th>Type</th><th>To</th><th>Subject</th><th>Status</th><th>By</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ \App\Models\EmailLog::TYPES[$log->type] ?? $log->type }}</td>
                    <td>{{ $log->to }}</td>
                    <td>{{ $log->subject }}</td>
                    <td>
                        <span class="badge {{ $log->statusClass() }}">{{ \App\Models\EmailLog::STATUSES[$log->status] ?? $log->status }}</span>
                        @if ($log->error)<div class="mt-1 max-w-sm text-xs text-red-700">{{ \Illuminate\Support\Str::limit($log->error, 200) }}</div>@endif
                    </td>
                    <td>{{ $log->user?->name ?? 'System' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-slate-500">No emails yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.app>
