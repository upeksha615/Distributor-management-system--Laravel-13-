<x-layouts.app :title="'Order: '.$customer->name">
    <x-page-header :title="'New order: '.$customer->name" :subtitle="($customer->price_level === 'wholesale' ? 'Dealer (wholesale) prices' : 'Retail prices').'. The order goes to the office for invoicing and delivery.'">
        <a href="{{ route('rep.visits.show', $visit) }}" class="btn btn-secondary">Back to visit</a>
    </x-page-header>

    <form method="POST" action="{{ route('rep.visits.order', $visit) }}" class="space-y-4" x-data
          onsubmit="this.querySelector('button[type=submit]').disabled = true">
        @csrf
        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
        <div class="card card-body grid gap-4 sm:grid-cols-3">
            <x-select label="Supply from" name="warehouse_id" :options="$warehouses->pluck('name', 'id')" :value="$warehouseId" required
                      x-on:change="$dispatch('warehouse-changed', $event.target.value)" />
            <x-field label="Notes for the office (delivery, urgency)" name="notes" class="sm:col-span-2" />
        </div>

        @include('partials.line-items', ['mode' => 'sale', 'items' => [], 'warehouseId' => $warehouseId, 'taxPercent' => $taxPercent, 'selectedCustomer' => $customer->id])

        <button type="submit" class="btn btn-primary w-full py-3 text-base sm:w-auto">Send order to office</button>
    </form>
</x-layouts.app>
