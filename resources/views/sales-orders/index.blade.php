<x-layouts.app title="Sales orders">
    <x-page-header title="Sales orders">
        <x-export-buttons />
        <a href="{{ route('sales-orders.create') }}" class="btn btn-primary">New sales order</a>
    </x-page-header>

    <x-search-bar placeholder="Order number or customer">
        <x-select label="Source" name="source" :options="['office' => 'Office', 'rep' => 'Rep (field)']" :value="request('source')" placeholder="All" class="w-36" />
        <x-select label="Status" name="status" :options="array_combine(\App\Models\SalesOrder::STATUSES, array_map('ucfirst', \App\Models\SalesOrder::STATUSES))" :value="request('status')" placeholder="All" class="w-36" />
    </x-search-bar>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Source</th><th>Status</th><th>Invoice</th><th class="num">Total</th></tr></thead>
            <tbody>
            @forelse ($orders as $o)
                <tr>
                    <td><a href="{{ route('sales-orders.show', $o) }}" class="link">{{ $o->number }}</a></td>
                    <td>{{ $o->order_date->format('d/m/Y') }}</td>
                    <td>{{ $o->customer->name }}</td>
                    <td>{{ $o->source === 'rep' ? 'Rep: '.$o->user?->name : 'Office' }}</td>
                    <td><x-status :status="$o->status" /></td>
                    <td>@if ($o->invoice)<a href="{{ route('invoices.show', $o->invoice) }}" class="link">{{ $o->invoice->number }}</a>@endif</td>
                    <td class="num">{{ money($o->total, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-500">No sales orders found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
</x-layouts.app>
