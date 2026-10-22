<x-layouts.app title="My visits">
    <x-page-header title="My visits">
        <a href="{{ route('rep.index') }}" class="btn btn-secondary">Rep dashboard</a>
    </x-page-header>

    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <x-field label="From" name="from" type="date" :value="request('from')" class="w-40" />
        <x-field label="To" name="to" type="date" :value="request('to')" class="w-40" />
        <button class="btn btn-secondary">Filter</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Date</th><th>Dealer</th><th class="num">Min</th><th>Outcome</th><th class="num">Orders</th><th class="num">Collected</th></tr></thead>
            <tbody>
            @forelse ($visits as $v)
                <tr>
                    <td class="whitespace-nowrap"><a href="{{ route('rep.visits.show', $v) }}" class="link">{{ $v->checked_in_at->format('d/m/Y H:i') }}</a></td>
                    <td>{{ $v->customer->name }}</td>
                    <td class="num">{{ $v->minutes() }}</td>
                    <td>{{ $v->isActive() ? 'Checked in' : $v->outcomeLabel() }}</td>
                    <td class="num">{{ money($v->orders->where('status', '!=', 'cancelled')->sum('total'), false) }}</td>
                    <td class="num">{{ money($v->payments->whereNotIn('status', ['rejected', 'reversed'])->sum('amount'), false) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-slate-500">No visits yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $visits->links() }}</div>
</x-layouts.app>
