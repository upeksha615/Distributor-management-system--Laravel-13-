<x-layouts.app title="Stock movements">
    <x-page-header title="Stock movements">
        <x-export-buttons />
    </x-page-header>

    <x-search-bar placeholder="SKU or product name">
        <x-select label="Warehouse" name="warehouse_id" :options="$warehouses->pluck('name', 'id')" :value="request('warehouse_id')" placeholder="All" class="w-40" />
        <x-select label="Type" name="type" :value="request('type')" placeholder="All" class="w-40"
                  :options="['purchase' => 'Purchase', 'sale' => 'Sale', 'return' => 'Return', 'adjustment' => 'Adjustment', 'transfer_in' => 'Transfer in', 'transfer_out' => 'Transfer out', 'cancel' => 'Cancelled invoice', 'shipment' => 'Shipment received']" />
        <x-field label="From" name="from" type="date" :value="request('from')" class="w-40" />
        <x-field label="To" name="to" type="date" :value="request('to')" class="w-40" />
    </x-search-bar>

    <div class="card">@include('stock._movements-table')</div>
    <div class="mt-4">{{ $movements->links() }}</div>
</x-layouts.app>
