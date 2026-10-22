<x-layouts.app title="Dealer payments">
    <x-page-header title="Dealer payments" subtitle="Only verified payments (and cleared cheques) count against invoices.">
        <x-export-buttons />
        <a href="{{ route('cheques.index') }}" class="btn btn-secondary">Cheque register</a>
        @can('payments.record')<a href="{{ route('payments.create') }}" class="btn btn-primary">Record payment</a>@endcan
    </x-page-header>

    <x-search-bar placeholder="Receipt, reference, cheque no or dealer">
        <x-select label="Status" name="status" :options="['awaiting' => 'Awaiting verification'] + \App\Models\Payment::STATUSES" :value="request('status')" placeholder="All" class="w-48" />
        <x-select label="Method" name="method" :options="\App\Models\Payment::METHODS" :value="request('method')" placeholder="All" class="w-44" />
        <x-select label="Channel" name="channel" :options="\App\Models\Payment::CHANNELS" :value="request('channel')" placeholder="All" class="w-36" />
        <x-field label="From" name="from" type="date" :value="request('from')" class="w-40" />
        <x-field label="To" name="to" type="date" :value="request('to')" class="w-40" />
    </x-search-bar>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Receipt</th><th>Date</th><th>Dealer</th><th>Method</th><th>Reference / cheque</th><th>Into account</th><th>Status</th><th class="num">Amount</th></tr></thead>
            <tbody>
            @forelse ($payments as $p)
                <tr>
                    <td><a href="{{ route('payments.show', $p) }}" class="link">{{ $p->number }}</a>
                        @if ($p->duplicate_check === 'flagged')<div><x-status status="possible duplicate" /></div>@endif</td>
                    <td>{{ $p->payment_date->format('d/m/Y') }}</td>
                    <td>{{ $p->customer->name }}</td>
                    <td>{{ $p->methodLabel() }}<div class="text-xs text-slate-500">{{ \App\Models\Payment::CHANNELS[$p->channel] ?? $p->channel }}</div></td>
                    <td class="font-mono text-xs">{{ $p->method === 'cheque' ? $p->cheque_number.' · '.$p->cheque_date?->format('d/m/Y') : $p->reference }}</td>
                    <td class="text-xs">{{ $p->bankAccount?->label() }}</td>
                    <td><x-status :status="$p->displayStatus()" /></td>
                    <td class="num">{{ money($p->amount, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-6 text-center text-slate-500">No payments found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</x-layouts.app>
