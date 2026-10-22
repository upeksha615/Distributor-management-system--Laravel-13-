<x-layouts.app :title="$order->exists ? 'Edit '.$order->number : 'New sales order'">
    <x-page-header :title="$order->exists ? 'Edit '.$order->number : 'New sales order'" subtitle="Orders do not affect stock until they are invoiced.">
        <a href="{{ $order->exists ? route('sales-orders.show', $order) : route('sales-orders.index') }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <form method="POST" action="{{ $order->exists ? route('sales-orders.update', $order) : route('sales-orders.store') }}" class="space-y-4" x-data>
        @csrf
        @if ($order->exists) @method('PUT') @endif
        <div class="card card-body grid gap-4 sm:grid-cols-4">
            <x-select label="Customer" name="customer_id" :options="$customers->mapWithKeys(fn ($c) => [$c->id => $c->name.' ('.$c->code.')'])" :value="$order->customer_id" required placeholder="Select customer..." class="sm:col-span-2"
                      x-on:change="$dispatch('customer-changed', $event.target.value)" />
            <x-select label="Warehouse" name="warehouse_id" :options="$warehouses->pluck('name', 'id')" :value="$warehouseId" required
                      x-on:change="$dispatch('warehouse-changed', $event.target.value)" />
            <x-field label="Order date" name="order_date" type="date" :value="$order->order_date?->format('Y-m-d')" required />
            <x-field label="Notes" name="notes" :value="$order->notes" class="sm:col-span-4" />
        </div>

        @include('partials.line-items', ['mode' => 'sale', 'items' => $items, 'warehouseId' => $warehouseId, 'discount' => $order->discount ?? 0, 'taxPercent' => $taxPercent, 'selectedCustomer' => $order->customer_id])

        <button class="btn btn-primary">Save order</button>
    </form>
</x-layouts.app>
