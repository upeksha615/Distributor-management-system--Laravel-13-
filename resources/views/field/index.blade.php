<x-layouts.app title="Field activity">
    <x-page-header title="Field activity" subtitle="Where the reps are, what they ordered and collected">
        <x-export-buttons />
        <form method="GET" class="flex items-end gap-2">
            <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="input w-40" onchange="this.form.submit()">
        </form>
    </x-page-header>

    {{-- Live: reps at dealers now (refreshes every 30 s) --}}
    <div class="card mb-6" x-data="{ t: null }" x-init="t = setInterval(() => { if (!document.hidden) location.reload() }, 30000)">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 class="font-semibold">At a dealer right now <span class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">{{ $active->count() }}</span></h2>
            <span class="text-xs text-slate-500">Live · refreshes every 30 seconds</span>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Rep</th><th>Dealer</th><th>Since</th><th class="num">Minutes</th><th>Location check</th><th></th></tr></thead>
                <tbody>
                @forelse ($active as $v)
                    @php $far = $v->distance_m !== null && $v->distance_m > \App\Http\Controllers\FieldController::FAR_METRES; @endphp
                    <tr class="{{ $far ? 'bg-red-50' : '' }}">
                        <td class="font-medium">{{ $v->rep->name }}</td>
                        <td>{{ $v->customer->name }}<div class="text-xs text-slate-500">{{ $v->customer->city }}</div></td>
                        <td>{{ $v->checked_in_at->format('H:i') }}</td>
                        <td class="num">{{ $v->minutes() }}</td>
                        <td>
                            @if ($v->check_in_lat === null)
                                <span class="text-amber-700">No GPS</span>
                            @elseif ($far)
                                <span class="font-semibold text-red-600">{{ number_format($v->distance_m) }} m away from dealer</span>
                            @else
                                <span class="text-emerald-700">{{ $v->distance_m ? 'At dealer ('.$v->distance_m.' m)' : 'Location saved' }}</span>
                            @endif
                            @if ($v->mapUrl()) · <a href="{{ $v->mapUrl() }}" target="_blank" class="link">map</a>@endif
                        </td>
                        <td><a href="{{ route('rep.visits.show', $v) }}" class="btn btn-secondary btn-sm">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-500">No rep is checked in at a dealer right now.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="card overflow-x-auto">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Reps on {{ $date->format('d/m/Y') }}</h2>
            <table class="table">
                <thead><tr><th>Rep</th><th class="num">Visits</th><th>First – last</th><th class="num">Orders</th><th class="num">Order value</th><th class="num">Collected</th></tr></thead>
                <tbody>
                @forelse ($repSummary as $r)
                    <tr>
                        <td><a href="{{ route('field.index', ['user_id' => $r['rep']->id, 'date' => $date->format('Y-m-d')]) }}" class="link">{{ $r['rep']->name }}</a></td>
                        <td class="num">{{ $r['visits'] }}</td>
                        <td class="text-slate-600">{{ $r['first'] ? \Illuminate\Support\Carbon::parse($r['first'])->format('H:i').' – '.\Illuminate\Support\Carbon::parse($r['last'])->format('H:i') : '-' }}</td>
                        <td class="num">{{ $r['orders'] }}</td>
                        <td class="num">{{ money($r['order_value'], false) }}</td>
                        <td class="num">{{ money($r['collected'], false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-500">No sales reps set up.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card overflow-x-auto">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Rep orders waiting to be invoiced <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{{ $pendingOrders->count() }}</span></h2>
            <table class="table">
                <thead><tr><th>Order</th><th>Dealer</th><th>Rep</th><th>Taken</th><th class="num">Total</th></tr></thead>
                <tbody>
                @forelse ($pendingOrders as $o)
                    <tr>
                        <td><a href="{{ route('sales-orders.show', $o) }}" class="link">{{ $o->number }}</a></td>
                        <td>{{ $o->customer->name }}</td>
                        <td>{{ $o->user?->name }}</td>
                        <td>{{ $o->created_at->format('d/m H:i') }}</td>
                        <td class="num">{{ money($o->total, false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-500">All rep orders are processed.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Visit log --}}
    <form method="GET" class="card card-body mt-6 mb-4 flex flex-wrap items-end gap-3">
        <x-select label="Rep" name="user_id" :options="$reps->pluck('name', 'id')" :value="request('user_id')" placeholder="All reps" class="w-44" />
        <x-select label="Outcome" name="outcome" :options="\App\Models\RepVisit::OUTCOMES" :value="request('outcome')" placeholder="All" class="w-44" />
        <x-field label="Dealer" name="q" :value="request('q')" class="w-44" />
        <x-field label="From" name="from" type="date" :value="request('from', $date->format('Y-m-d'))" class="w-40" />
        <x-field label="To" name="to" type="date" :value="request('to', $date->format('Y-m-d'))" class="w-40" />
        <button class="btn btn-secondary">Show visits</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Rep</th><th>Dealer</th><th>In – out</th><th class="num">Min</th><th>Location</th><th>Outcome / notes</th><th class="num">Orders</th><th class="num">Collected</th></tr></thead>
            <tbody>
            @forelse ($log as $v)
                @php $far = $v->distance_m !== null && $v->distance_m > \App\Http\Controllers\FieldController::FAR_METRES; @endphp
                <tr>
                    <td>{{ $v->rep->name }}</td>
                    <td><a href="{{ route('rep.visits.show', $v) }}" class="link">{{ $v->customer->name }}</a></td>
                    <td class="whitespace-nowrap">{{ $v->checked_in_at->format('d/m H:i') }} – {{ $v->checked_out_at?->format('H:i') ?? 'now' }}</td>
                    <td class="num">{{ $v->minutes() }}</td>
                    <td class="{{ $far ? 'font-semibold text-red-600' : 'text-slate-600' }}">{{ $v->check_in_lat === null ? 'No GPS' : ($v->distance_m !== null ? number_format($v->distance_m).' m' : '-') }}</td>
                    <td>{{ $v->outcomeLabel() ?? 'Checked in' }}@if ($v->notes)<div class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($v->notes, 120) }}</div>@endif</td>
                    <td class="num">{{ money($v->orders->where('status', '!=', 'cancelled')->sum('total'), false) }}</td>
                    <td class="num">{{ money($v->payments->whereNotIn('status', ['rejected', 'reversed'])->sum('amount'), false) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-6 text-center text-slate-500">No visits in this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $log->links() }}</div>
</x-layouts.app>
