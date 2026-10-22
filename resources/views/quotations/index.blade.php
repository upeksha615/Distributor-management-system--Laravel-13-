<x-layouts.app title="Quotations">
    <x-page-header title="Quotations">
        <x-export-buttons />
        <a href="{{ route('quotations.create') }}" class="btn btn-primary">New quotation</a>
    </x-page-header>

    <x-search-bar placeholder="Quote number or customer">
        <x-select label="Status" name="status" :options="['open' => 'Open', 'expired' => 'Expired', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'converted' => 'Converted']" :value="request('status')" placeholder="All" class="w-36" />
    </x-search-bar>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Quote</th><th>Date</th><th>Valid until</th><th>Customer</th><th>Status</th><th>Converted to</th><th class="num">Total</th></tr></thead>
            <tbody>
            @forelse ($quotes as $q)
                <tr>
                    <td><a href="{{ route('quotations.show', $q) }}" class="link">{{ $q->number }}</a></td>
                    <td>{{ $q->quote_date->format('d/m/Y') }}</td>
                    <td>{{ $q->valid_until?->format('d/m/Y') }}</td>
                    <td>{{ $q->customer->name }}</td>
                    <td>@include('quotations._status', ['quote' => $q])</td>
                    <td>
                        @if ($q->salesOrder)<a href="{{ route('sales-orders.show', $q->salesOrder) }}" class="link">{{ $q->salesOrder->number }}</a>@endif
                        @if ($q->invoice)<a href="{{ route('invoices.show', $q->invoice) }}" class="link">{{ $q->invoice->number }}</a>@endif
                    </td>
                    <td class="num">{{ money($q->total, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-500">No quotations found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $quotes->links() }}</div>
</x-layouts.app>
