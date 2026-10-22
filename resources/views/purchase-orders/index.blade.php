<x-layouts.app title="Purchase orders">
    <x-page-header title="Purchase orders">
        <x-export-buttons />
        <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">New purchase order</a>
    </x-page-header>

    <x-search-bar placeholder="PO number">
        <x-select label="Supplier" name="supplier_id" :options="$suppliers->pluck('name', 'id')" :value="request('supplier_id')" placeholder="All" class="w-48" />
        <x-select label="Status" name="status" :options="array_combine(\App\Models\PurchaseOrder::STATUSES, array_map('ucfirst', \App\Models\PurchaseOrder::STATUSES))" :value="request('status')" placeholder="All" class="w-36" />
    </x-search-bar>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>PO</th><th>Date</th><th>Supplier</th><th>Warehouse</th><th>Expected</th><th>Status</th><th class="num">Total</th></tr></thead>
            <tbody>
            @forelse ($orders as $po)
                <tr>
                    <td><a href="{{ route('purchase-orders.show', $po) }}" class="link">{{ $po->number }}</a></td>
                    <td>{{ $po->order_date->format('d/m/Y') }}</td>
                    <td>{{ $po->supplier->name }}</td>
                    <td>{{ $po->warehouse->name }}</td>
                    <td>{{ $po->expected_date?->format('d/m/Y') }}</td>
                    <td><x-status :status="$po->status" /></td>
                    <td class="num">{{ money($po->total, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-500">No purchase orders found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
</x-layouts.app>
