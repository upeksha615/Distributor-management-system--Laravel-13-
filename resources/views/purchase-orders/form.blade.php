<x-layouts.app :title="$po->exists ? 'Edit '.$po->number : 'New purchase order'">
    <x-page-header :title="$po->exists ? 'Edit '.$po->number : 'New purchase order'">
        <a href="{{ $po->exists ? route('purchase-orders.show', $po) : route('purchase-orders.index') }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <form method="POST" action="{{ $po->exists ? route('purchase-orders.update', $po) : route('purchase-orders.store') }}" class="space-y-4" x-data>
        @csrf
        @if ($po->exists) @method('PUT') @endif
        <div class="card card-body grid gap-4 sm:grid-cols-4">
            <x-select label="Supplier" name="supplier_id" :options="$suppliers->pluck('name', 'id')" :value="$po->supplier_id" required placeholder="Select supplier..." class="sm:col-span-2" />
            <x-select label="Receive into" name="warehouse_id" :options="$warehouses->pluck('name', 'id')" :value="$po->warehouse_id" required
                      x-on:change="$dispatch('warehouse-changed', $event.target.value)" />
            <div></div>
            <x-field label="Order date" name="order_date" type="date" :value="$po->order_date?->format('Y-m-d')" required />
            <x-field label="Expected delivery" name="expected_date" type="date" :value="$po->expected_date?->format('Y-m-d')" />
            <x-field label="Notes" name="notes" :value="$po->notes" class="sm:col-span-2" />
        </div>

        @include('partials.line-items', ['mode' => 'purchase', 'items' => $items, 'warehouseId' => $po->warehouse_id ?? $warehouses->first()?->id])

        <button class="btn btn-primary">Save as draft</button>
    </form>
</x-layouts.app>
