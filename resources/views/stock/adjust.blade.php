<x-layouts.app title="Adjust stock">
    <x-page-header title="Adjust stock" subtitle="Stock take, opening stock, damages or corrections" />

    <form method="POST" action="{{ route('stock.adjust') }}" class="space-y-4" x-data="{ mode: '{{ old('mode', 'set') }}' }">
        @csrf
        <div class="card card-body grid gap-4 sm:grid-cols-3">
            <x-select label="Warehouse" name="warehouse_id" :options="$warehouses->pluck('name', 'id')" required
                      x-on:change="$dispatch('warehouse-changed', $event.target.value)" :value="$warehouses->first()?->id" />
            <div>
                <label class="label">Adjustment type</label>
                <select name="mode" x-model="mode" class="input">
                    <option value="set">Set counted quantity (stock take)</option>
                    <option value="add">Add / remove quantity (+ / -)</option>
                </select>
                <p class="mt-1 text-xs text-slate-500" x-show="mode === 'set'">Enter the quantity physically counted. The difference is posted.</p>
                <p class="mt-1 text-xs text-slate-500" x-show="mode === 'add'" x-cloak>Use negative numbers to remove stock, e.g. -2 for damaged items.</p>
            </div>
            <x-field label="Reason" name="reason" required placeholder="e.g. Monthly stock count, Opening stock, Damaged" />
        </div>

        @include('partials.line-items', ['mode' => 'stock', 'quantityHeader' => 'Quantity', 'allowNegativeQty' => true, 'warehouseId' => $warehouses->first()?->id])

        <button class="btn btn-primary">Post adjustment</button>
    </form>
</x-layouts.app>
