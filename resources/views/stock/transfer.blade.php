<x-layouts.app title="Transfer stock">
    <x-page-header title="Transfer stock" subtitle="Move stock between warehouses" />

    <form method="POST" action="{{ route('stock.transfer') }}" class="space-y-4" x-data>
        @csrf
        <div class="card card-body grid gap-4 sm:grid-cols-3">
            <x-select label="From warehouse" name="from_warehouse_id" :options="$warehouses->pluck('name', 'id')" required
                      x-on:change="$dispatch('warehouse-changed', $event.target.value)" :value="$warehouses->first()?->id" />
            <x-select label="To warehouse" name="to_warehouse_id" :options="$warehouses->pluck('name', 'id')" required placeholder="Select..." />
            <x-field label="Note" name="note" />
        </div>

        @include('partials.line-items', ['mode' => 'stock', 'quantityHeader' => 'Quantity to move', 'warehouseField' => 'from_warehouse_id', 'warehouseId' => $warehouses->first()?->id])

        <button class="btn btn-primary">Transfer</button>
    </form>
</x-layouts.app>
