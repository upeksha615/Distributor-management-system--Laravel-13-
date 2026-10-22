<x-layouts.app title="Audit log">
    <x-page-header title="Audit log" subtitle="Sensitive actions: cancellations, price changes, stock adjustments, returns, shift differences, user changes.">
        <x-export-buttons />
    </x-page-header>

    <x-search-bar placeholder="Search details">
        <x-select label="User" name="user_id" :options="$users->pluck('name', 'id')" :value="request('user_id')" placeholder="All" class="w-40" />
        <x-select label="Action" name="action" :options="$actions->combine($actions)" :value="request('action')" placeholder="All" class="w-44" />
        <x-field label="From" name="from" type="date" :value="request('from')" class="w-40" />
        <x-field label="To" name="to" type="date" :value="request('to')" class="w-40" />
    </x-search-bar>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th class="w-40">Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $log->user?->name ?? 'System' }}</td>
                    <td><span class="badge bg-slate-100 text-slate-700">{{ $log->action }}</span></td>
                    <td>{{ $log->description }}</td>
                    <td class="text-xs text-slate-500">{{ $log->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-slate-500">Nothing logged yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.app>
