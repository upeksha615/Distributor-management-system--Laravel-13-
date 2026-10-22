<x-layouts.app title="Verify payments">
    <x-page-header title="Verify payments" subtitle="Check each payment against the company bank statement before verifying. Oldest first.">
        <a href="{{ route('payments.index') }}" class="btn btn-secondary">All payments</a>
    </x-page-header>

    <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-stat label="Awaiting verification" :value="$summary['count']" :tone="$summary['count'] ? 'amber' : 'slate'" />
        <x-stat label="Amount" :value="money($summary['amount'])" />
        <x-stat label="Under review (info requested)" :value="$summary['under_review']" />
        <x-stat label="Possible duplicates" :value="$summary['flagged']" :tone="$summary['flagged'] ? 'red' : 'slate'" :href="route('payment-verification.index', ['flag' => 'duplicates'])" />
        <x-stat label="Drafts (not submitted)" :value="$summary['drafts']" />
    </div>

    <div class="mb-4 rounded-md bg-slate-50 px-4 py-2 text-xs text-slate-600">
        Rules: {{ $segregation ? 'you cannot verify payments you entered; ' : '' }}{{ $limit > 0 ? 'payments above '.money($limit).' need a user with "verify above limit"; ' : '' }}cheques settle invoices {{ config('distributor.cheque_recognition') === 'on_clearance' ? 'only when cleared' : 'when verified' }}.
    </div>

    <x-search-bar placeholder="Receipt, reference, cheque no or dealer">
        <x-select label="Method" name="method" :options="\App\Models\Payment::METHODS" :value="request('method')" placeholder="All" class="w-44" />
        <x-select label="Into account" name="account" :options="$accounts" :value="request('account')" placeholder="All" class="w-56" />
        <x-select label="Show" name="flag" :options="['mine' => 'Not entered by me', 'duplicates' => 'Possible duplicates']" :value="request('flag')" placeholder="Everything" class="w-44" />
    </x-search-bar>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Receipt</th><th>Date</th><th>Dealer</th><th>Method</th><th>Reference / cheque</th><th>Into account</th><th>Entered by</th><th>Status</th><th class="num">Amount</th><th></th></tr></thead>
            <tbody>
            @forelse ($payments as $p)
                @php $own = $segregation && $p->user_id === auth()->id(); $over = $limit > 0 && $p->amount > $limit && ! auth()->user()->can('payments.verify_above_limit'); @endphp
                <tr class="{{ $p->duplicate_check === 'flagged' ? 'bg-red-50' : '' }}">
                    <td><a href="{{ route('payments.show', $p) }}" class="link">{{ $p->number }}</a>
                        <div class="text-xs text-slate-500">{{ $p->attachments_count }} file(s) · {{ \App\Models\Payment::CHANNELS[$p->channel] ?? $p->channel }}</div></td>
                    <td>{{ $p->payment_date->format('d/m/Y') }}<div class="text-xs text-slate-500">{{ $p->created_at->diffForHumans() }}</div></td>
                    <td>{{ $p->customer->name }}</td>
                    <td>{{ $p->methodLabel() }}</td>
                    <td class="font-mono text-xs">{{ $p->method === 'cheque' ? $p->cheque_number.' · '.($p->bank?->displayName()) : $p->reference }}</td>
                    <td class="text-xs">{{ $p->bankAccount?->label() }}</td>
                    <td>{{ $p->user?->name }}</td>
                    <td><x-status :status="$p->displayStatus()" />
                        @if ($p->duplicate_check === 'flagged')<div><x-status status="possible duplicate" /></div>@endif</td>
                    <td class="num font-medium">{{ money($p->amount, false) }}</td>
                    <td class="text-right">
                        @if ($own)<span class="text-xs text-slate-500">Your entry</span>
                        @elseif ($over)<span class="text-xs text-amber-700">Above limit</span>
                        @else<a href="{{ route('payments.show', $p) }}" class="btn btn-primary btn-sm">Review</a>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="py-8 text-center text-slate-500">Nothing waiting for verification.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</x-layouts.app>
