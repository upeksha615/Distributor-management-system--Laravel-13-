<x-layouts.app title="Upcoming shipments">
    <x-page-header title="Upcoming shipments" subtitle="Goods on the way. Reps see these quantities in the Ship column. They become stock only when received.">
        @can('shipments.manage')<a href="{{ route('shipments.create') }}" class="btn btn-primary">Upload shipment</a>@endcan
    </x-page-header>

    <form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-48 flex-1"><label class="label">Search</label><input type="search" name="q" value="{{ request('q') }}" placeholder="Shipment no or reference" class="input"></div>
        <x-select label="Status" name="status" :options="['open' => 'Pending / partly received'] + \App\Models\Shipment::STATUSES + ['all' => 'All']" :value="$status" class="w-56" />
        <button class="btn btn-secondary">Filter</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Shipment</th><th>Reference</th><th>Supplier</th><th>To warehouse</th><th>Expected</th><th class="num">Products</th>@can('stock.view_qty')<th class="num">Units</th><th class="num">Received</th>@endcan<th>Status</th></tr></thead>
            <tbody>
            @forelse ($shipments as $s)
                <tr>
                    <td><a href="{{ route('shipments.show', $s) }}" class="link">{{ $s->number }}</a><div class="text-xs text-slate-500">{{ $s->created_at->format('d/m/Y') }} · {{ $s->user?->name }}</div></td>
                    <td>{{ $s->reference }}</td>
                    <td>{{ $s->supplier?->name }}</td>
                    <td>{{ $s->warehouse->name }}</td>
                    <td class="{{ $s->isOpen() && $s->expected_date?->isPast() ? 'text-red-600' : '' }}">{{ $s->expected_date?->format('d/m/Y') ?? '-' }}</td>
                    <td class="num">{{ $s->items_count }}</td>
                    @can('stock.view_qty')
                        <td class="num">{{ qty($s->total_qty) }}</td>
                        <td class="num">{{ qty($s->received_qty) }}</td>
                    @endcan
                    <td><span class="badge {{ ['pending' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200', 'partial' => 'bg-amber-100 text-amber-800', 'received' => 'bg-slate-100 text-slate-700', 'cancelled' => 'bg-slate-200 text-slate-500'][$s->status] }}">{{ \App\Models\Shipment::STATUSES[$s->status] }}</span></td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-8 text-center text-slate-500">No shipments.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $shipments->links() }}</div>
</x-layouts.app>
