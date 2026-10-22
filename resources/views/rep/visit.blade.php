<x-layouts.app :title="'Visit: '.$customer->name">
    @include('rep._gps')
    @php
        $mine = $visit->user_id === auth()->id();
        $canAct = $visit->isActive() && $mine;
        $overdue = $openInvoices->filter(fn ($i) => $i->daysOverdue() > 0);
    @endphp

    <x-page-header :title="$customer->name" :subtitle="$customer->code.' · '.($customer->city ?: '').' · '.$customer->phone">
        <a href="{{ route('rep.index') }}" class="btn btn-secondary">Rep dashboard</a>
        @if (auth()->user()->can('field.monitor'))
            <a href="{{ route('field.index') }}" class="btn btn-secondary">Field activity</a>
        @endif
    </x-page-header>

    {{-- Visit status --}}
    <div class="mb-4 rounded-lg border p-4 {{ $visit->isActive() ? 'border-emerald-400 bg-emerald-50' : 'border-slate-200 bg-white' }}">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="font-semibold">
                    {{ $visit->rep->name }} · {{ $visit->isActive() ? 'checked in' : 'visited' }} {{ $visit->checked_in_at->format('d/m/Y H:i') }}
                    @if (! $visit->isActive()) – {{ $visit->checked_out_at->format('H:i') }} @endif
                    ({{ $visit->minutes() }} min)
                </div>
                <div class="text-sm text-slate-600">
                    @if ($visit->check_in_lat)
                        Location: <a href="{{ $visit->mapUrl() }}" target="_blank" class="link">map</a>
                        @if ($visit->check_in_accuracy) (±{{ $visit->check_in_accuracy }} m) @endif
                        @if ($visit->distance_m !== null)
                            · <span class="{{ $visit->distance_m > \App\Http\Controllers\FieldController::FAR_METRES ? 'font-semibold text-red-600' : 'text-emerald-700' }}">
                                {{ $visit->distance_m === 0 ? 'dealer location saved from this visit' : number_format($visit->distance_m).' m from dealer' }}</span>
                        @endif
                    @else
                        <span class="text-amber-700">No GPS location recorded.</span>
                    @endif
                </div>
                @if ($visit->outcome)<div class="mt-1 text-sm">Outcome: <strong>{{ $visit->outcomeLabel() }}</strong>@if ($visit->next_visit_date) · next visit {{ $visit->next_visit_date->format('d/m/Y') }}@endif</div>@endif
                @if ($visit->notes)<div class="mt-1 text-sm text-slate-700">“{{ $visit->notes }}”</div>@endif
            </div>
            @if ($canAct)
                <a href="{{ route('rep.visits.order', $visit) }}" class="btn btn-primary px-6 py-3 text-base">Take order</a>
            @endif
        </div>
    </div>

    @if ($customer->on_hold)
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">This dealer is <strong>on hold</strong>. Orders will need office approval; collect payment first.</div>
    @endif

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Balance owing" :value="money($balance)" :tone="$balance > 0 ? 'red' : 'green'" />
        <x-stat label="Overdue" :value="money($overdue->sum(fn ($i) => $i->balance()))" :tone="$overdue->isNotEmpty() ? 'red' : 'slate'" />
        <x-stat label="Credit limit" :value="$customer->credit_limit > 0 ? money($customer->credit_limit) : 'No limit'" />
        <x-stat label="Available credit" :value="$customer->credit_limit > 0 ? money($customer->credit_limit - $balance) : '-'" :tone="$customer->credit_limit > 0 && $balance > $customer->credit_limit ? 'red' : 'slate'" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- This visit --}}
        <div class="card">
            <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">This visit</h2>
            <div class="divide-y divide-slate-100 text-sm">
                @forelse ($visit->orders as $o)
                    <div class="flex justify-between px-4 py-2"><span>Order <a href="{{ route('sales-orders.show', $o) }}" class="link">{{ $o->number }}</a> <x-status :status="$o->status" /></span><span class="tabular-nums">{{ money($o->total, false) }}</span></div>
                @empty
                    <div class="px-4 py-2 text-slate-500">No orders yet.</div>
                @endforelse
                @foreach ($visit->payments as $p)
                    <div class="flex justify-between px-4 py-2"><span>Receipt <a href="{{ route('payments.show', $p) }}" class="link">{{ $p->number }}</a> · {{ $p->methodLabel() }}</span><span class="tabular-nums">{{ money($p->amount, false) }}</span></div>
                @endforeach
            </div>

            @if ($canAct)
                {{-- Collect payment --}}
                <form method="POST" action="{{ route('rep.visits.payment', $visit) }}" class="space-y-3 border-t border-slate-200 p-4" x-data="{ method: 'cash' }"
                      onsubmit="return confirm('Record this payment from {{ addslashes($customer->name) }}?')">
                    @csrf
                    <div class="font-semibold">Collect payment</div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="label">Amount</label><input type="number" step="0.01" min="0.01" name="amount" class="input" required></div>
                        <div><label class="label">Method</label>
                            <select name="method" x-model="method" class="input"><option value="cash">Cash</option><option value="cheque">Cheque</option><option value="bank_transfer">Bank transfer</option></select></div>
                    </div>
                    <template x-if="method === 'cheque'">
                        <div class="grid grid-cols-3 gap-2">
                            <input name="cheque_number" placeholder="Cheque no" class="input" required>
                            <select name="bank_id" class="input" required><option value="">Bank...</option>@foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->displayName() }}</option>@endforeach</select>
                            <input type="date" name="cheque_date" class="input" required>
                        </div>
                    </template>
                    <template x-if="method === 'bank_transfer'"><input name="reference" placeholder="Transfer reference" class="input"></template>
                    <p class="text-xs text-slate-500">Proposed against the oldest unpaid invoices. Hand the money / cheque to the office: it counts after the accountant verifies it.</p>
                    <button class="btn btn-success">Save payment</button>
                </form>
            @endif
        </div>

        {{-- Check out --}}
        @if ($canAct)
            <form method="POST" action="{{ route('rep.visits.checkout', $visit) }}" class="card card-body space-y-3" x-data="gpsForm" @submit.prevent="submit($el)">
                @csrf
                <input type="hidden" name="lat"><input type="hidden" name="lng">
                <div class="font-semibold">Check out</div>
                @php $suggest = $visit->orders->isNotEmpty() ? ($visit->payments->isNotEmpty() ? 'order_payment' : 'order') : ($visit->payments->isNotEmpty() ? 'payment' : ''); @endphp
                <x-select label="Outcome" name="outcome" :options="\App\Models\RepVisit::OUTCOMES" :value="$suggest" placeholder="Select..." required />
                <x-field label="Notes (stock check, competitor prices, complaints, what they need)" name="notes" type="textarea" />
                <x-field label="Next visit" name="next_visit_date" type="date" :value="today()->addDays(7)->format('Y-m-d')" />
                <button class="btn btn-primary w-full py-3" :disabled="locating">Check out</button>
            </form>
        @endif

        {{-- Open invoices --}}
        <div class="card overflow-x-auto">
            <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">Unpaid invoices</h2>
            <table class="table">
                <thead><tr><th>Invoice</th><th>Due</th><th class="num">Late</th><th class="num">Balance</th></tr></thead>
                <tbody>
                @forelse ($openInvoices as $inv)
                    <tr>
                        <td>{{ $inv->number }}<div class="text-xs text-slate-500">{{ $inv->invoice_date->format('d/m/Y') }}</div></td>
                        <td>{{ $inv->due_date->format('d/m/Y') }}</td>
                        <td class="num {{ $inv->daysOverdue() ? 'text-red-600' : '' }}">{{ $inv->daysOverdue() ? $inv->daysOverdue().'d' : '-' }}</td>
                        <td class="num">{{ money($inv->balance(), false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-slate-500">Nothing outstanding.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- What they usually buy --}}
        <div class="card overflow-x-auto">
            <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">Usually buys (last 6 months)</h2>
            <table class="table">
                <thead><tr><th>Product</th><th class="num">Qty</th><th>Last</th></tr></thead>
                <tbody>
                @forelse ($topProducts as $tp)
                    <tr><td>{{ $tp->sku }}<div class="text-xs text-slate-500">{{ $tp->name }}</div></td><td class="num">{{ qty($tp->qty) }}</td><td>{{ \Illuminate\Support\Carbon::parse($tp->last_date)->format('d/m/y') }}</td></tr>
                @empty
                    <tr><td colspan="3" class="py-4 text-center text-slate-500">No purchase history.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card overflow-x-auto">
            <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">Previous visits</h2>
            <table class="table">
                <tbody>
                @forelse ($previousVisits as $pv)
                    <tr>
                        <td class="whitespace-nowrap">{{ $pv->checked_in_at->format('d/m/Y') }}<div class="text-xs text-slate-500">{{ $pv->rep->name }}</div></td>
                        <td>{{ $pv->outcomeLabel() ?? 'In progress' }}@if ($pv->notes)<div class="text-xs text-slate-500">{{ $pv->notes }}</div>@endif</td>
                    </tr>
                @empty
                    <tr><td class="py-4 text-center text-slate-500">First visit.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card overflow-x-auto">
            <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">Recent orders</h2>
            <table class="table">
                <tbody>
                @forelse ($recentOrders as $o)
                    <tr><td>{{ $o->number }}<div class="text-xs text-slate-500">{{ $o->order_date->format('d/m/Y') }}</div></td><td><x-status :status="$o->status" /></td><td class="num">{{ money($o->total, false) }}</td></tr>
                @empty
                    <tr><td class="py-4 text-center text-slate-500">No orders yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
