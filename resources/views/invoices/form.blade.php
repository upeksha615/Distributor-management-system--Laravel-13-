<x-layouts.app title="New invoice">
    <x-page-header title="New invoice" subtitle="Stock is deducted when the invoice is saved.">
        <a href="{{ route('invoices.index') }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <form method="POST" action="{{ route('invoices.store') }}" class="space-y-4" x-data onsubmit="this.querySelector('button[type=submit]').disabled = true">
        @csrf
        <div class="card card-body grid gap-4 sm:grid-cols-4">
            <x-select label="Customer" name="customer_id" :options="$customers->mapWithKeys(fn ($c) => [$c->id => $c->name.' ('.$c->code.')'.($c->on_hold ? ' - ON HOLD' : '')])" :value="$customerId" required placeholder="Select customer..." class="sm:col-span-2"
                      x-on:change="$dispatch('customer-changed', $event.target.value)" />
            <x-select label="Warehouse" name="warehouse_id" :options="$warehouses->pluck('name', 'id')" :value="$warehouseId" required
                      x-on:change="$dispatch('warehouse-changed', $event.target.value)" />
            <x-field label="Invoice date" name="invoice_date" type="date" :value="today()->format('Y-m-d')" required />
            <x-field label="Notes" name="notes" class="sm:col-span-4" />
        </div>

        @include('partials.line-items', ['mode' => 'sale', 'items' => [], 'warehouseId' => $warehouseId, 'taxPercent' => $taxPercent, 'selectedCustomer' => $customerId])

        <div class="flex flex-wrap items-center gap-4">
            <button type="submit" class="btn btn-primary">Create invoice</button>
            @if (auth()->user()->can('invoices.override_credit'))
                <x-checkbox label="Override credit limit / hold" name="override_credit" />
            @endif
        </div>
    </form>
</x-layouts.app>
