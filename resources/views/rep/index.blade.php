<x-layouts.app title="Rep dashboard">
    @include('rep._gps')
    <x-page-header title="Rep dashboard" :subtitle="auth()->user()->name.' · '.now()->format('l, j F')">
        <a href="{{ route('rep.history') }}" class="btn btn-secondary">My visits</a>
    </x-page-header>

    {{-- Current visit --}}
    @if ($active)
        <a href="{{ route('rep.visits.show', $active) }}" class="mb-4 block rounded-lg border-2 border-emerald-500 bg-emerald-50 p-4">
            <div class="text-xs font-semibold uppercase text-emerald-700">Checked in now · {{ $active->minutes() }} min</div>
            <div class="text-lg font-semibold text-slate-900">{{ $active->customer->name }}</div>
            <div class="text-sm text-slate-600">Since {{ $active->checked_in_at->format('H:i') }}. Tap to take an order, collect payment or check out.</div>
        </a>
    @endif

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Visits today" :value="$today['visits']" />
        <x-stat label="Orders today" :value="$today['orders']" />
        <x-stat label="Order value" :value="money($today['order_value'])" tone="green" />
        <x-stat label="Collected" :value="money($today['collected'])" />
    </div>

    @if ($dueVisits->isNotEmpty())
        <div class="card mb-4">
            <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">Due for a visit</h2>
            @foreach ($dueVisits as $v)
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2 text-sm">
                    <span>{{ $v->customer->name }} <span class="text-slate-500">· {{ $v->customer->city }}</span></span>
                    <span class="text-amber-700">planned {{ $v->next_visit_date->format('d/m') }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Dealers --}}
    <div class="card">
        <div class="border-b border-slate-200 p-4">
            <form method="GET" class="flex gap-2">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search dealer, code, phone" class="input">
                @if (! auth()->user()->canSeeAllDealers())
                    <label class="flex shrink-0 items-center gap-1 text-sm"><input type="checkbox" name="all" value="1" @checked($seeAll) onchange="this.form.submit()"> All dealers</label>
                @endif
                <button class="btn btn-secondary">Search</button>
            </form>
            <p class="mt-1 text-xs text-slate-500">{{ $seeAll ? 'Showing all dealers.' : 'Showing your dealers.' }} Tap <strong>Check in</strong> when you arrive at the shop.</p>
        </div>
        @forelse ($dealers as $d)
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-4 py-3">
                <div class="min-w-0 flex-1">
                    <div class="font-medium">{{ $d->name }} @if ($d->on_hold)<x-status status="on hold" />@endif</div>
                    <div class="text-xs text-slate-500">
                        {{ $d->code }} · {{ $d->city ?: '-' }} · {{ $d->phone }}
                        · Balance <span class="{{ $d->balance > 0 ? 'text-red-600' : '' }}">{{ money($d->balance, false) }}</span>
                        · Last visit {{ $d->last_visit ? \Illuminate\Support\Carbon::parse($d->last_visit)->diffForHumans() : 'never' }}
                    </div>
                </div>
                @if ($d->phone)<a href="tel:{{ $d->phone }}" class="btn btn-secondary btn-sm">Call</a>@endif
                @unless ($active)
                    <form method="POST" action="{{ route('rep.visits.store') }}" x-data="gpsForm" @submit.prevent="submit($el)">
                        @csrf
                        <input type="hidden" name="customer_id" value="{{ $d->id }}">
                        <input type="hidden" name="lat"><input type="hidden" name="lng"><input type="hidden" name="accuracy">
                        <button class="btn btn-success btn-sm" :disabled="locating"><span x-show="!locating">Check in</span><span x-show="locating" x-cloak>Getting location…</span></button>
                    </form>
                @endunless
            </div>
        @empty
            <div class="px-4 py-8 text-center text-slate-500">No dealers found. {{ $seeAll ? '' : 'Ask the office to assign dealers to you, or tick "All dealers".' }}</div>
        @endforelse
    </div>

    @if ($todayVisits->isNotEmpty())
        <div class="card mt-4 overflow-x-auto">
            <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">Today's visits</h2>
            <table class="table">
                <thead><tr><th>Time</th><th>Dealer</th><th>Minutes</th><th>Outcome</th></tr></thead>
                <tbody>
                @foreach ($todayVisits as $v)
                    <tr>
                        <td><a href="{{ route('rep.visits.show', $v) }}" class="link">{{ $v->checked_in_at->format('H:i') }}</a></td>
                        <td>{{ $v->customer->name }}</td>
                        <td>{{ $v->minutes() }}</td>
                        <td>{!! $v->isActive() ? '<span class="text-emerald-700">Checked in</span>' : e($v->outcomeLabel()) !!}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>
