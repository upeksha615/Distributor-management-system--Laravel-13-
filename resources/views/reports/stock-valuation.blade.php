<x-layouts.app title="Stock valuation">
    <x-page-header title="Stock valuation" subtitle="On-hand quantity at current cost and selling price">
        <x-export-buttons />
        <form method="GET">
            <select name="warehouse_id" class="input w-48" onchange="this.form.submit()">
                <option value="">All warehouses</option>
                @foreach ($warehouses as $w)<option value="{{ $w->id }}" @selected(request('warehouse_id') == $w->id)>{{ $w->name }}</option>@endforeach
            </select>
        </form>
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </x-page-header>

    @php
        $costValue = $rows->sum(fn ($r) => $r->qty * $r->cost_price);
        $saleValue = $rows->sum(fn ($r) => $r->qty * $r->selling_price);
    @endphp
    <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-3">
        <x-stat label="Items in stock" :value="$rows->count()" />
        <x-stat label="Value at cost" :value="money($costValue)" />
        <x-stat label="Value at selling price" :value="money($saleValue)" tone="green" />
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>SKU</th><th>Name</th><th>Brand</th><th class="num">Qty</th><th class="num">Unit cost</th><th class="num">Value at cost</th><th class="num">Value at price</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td><a href="{{ route('products.show', $r->id) }}" class="link">{{ $r->sku }}</a></td>
                    <td>{{ $r->name }}</td>
                    <td>{{ $r->brand }}</td>
                    <td class="num {{ $r->qty < 0 ? 'text-red-600' : '' }}">{{ qty($r->qty) }}</td>
                    <td class="num">{{ money($r->cost_price, false) }}</td>
                    <td class="num">{{ money($r->qty * $r->cost_price, false) }}</td>
                    <td class="num">{{ money($r->qty * $r->selling_price, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-500">No stock on hand.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
