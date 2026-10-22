<x-layouts.app :title="$quote->exists ? 'Edit '.$quote->number : 'New quotation'">
    <x-page-header :title="$quote->exists ? 'Edit '.$quote->number : 'New quotation'" subtitle="Quotations do not affect stock or the customer balance.">
        <a href="{{ $quote->exists ? route('quotations.show', $quote) : route('quotations.index') }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <form method="POST" action="{{ $quote->exists ? route('quotations.update', $quote) : route('quotations.store') }}" class="space-y-4" x-data>
        @csrf
        @if ($quote->exists) @method('PUT') @endif
        <div class="card card-body grid gap-4 sm:grid-cols-4">
            <x-select label="Customer" name="customer_id" :options="$customers->mapWithKeys(fn ($c) => [$c->id => $c->name.' ('.$c->code.')'])" :value="$quote->customer_id" required placeholder="Select customer..." class="sm:col-span-2" />
            <x-select label="Warehouse" name="warehouse_id" :options="$warehouses->pluck('name', 'id')" :value="$warehouseId" required
                      x-on:change="$dispatch('warehouse-changed', $event.target.value)" />
            <x-field label="Quote date" name="quote_date" type="date" :value="$quote->quote_date?->format('Y-m-d')" required />
            <x-field label="Valid until" name="valid_until" type="date" :value="$quote->valid_until?->format('Y-m-d')" help="Blank = 14 days after the quote date." />
            <x-field label="Notes" name="notes" :value="$quote->notes" class="sm:col-span-3" />
        </div>

        @include('partials.line-items', ['mode' => 'sale', 'items' => $items, 'warehouseId' => $warehouseId, 'discount' => $quote->discount ?? 0, 'taxPercent' => $taxPercent])

        <button class="btn btn-primary">Save quotation</button>
    </form>
</x-layouts.app>
