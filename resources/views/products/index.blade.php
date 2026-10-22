<x-layouts.app title="Products">
    @php
        $seeCost = auth()->user()->can('products.view_cost');
        $low = config('distributor.stock_low_threshold', 10);
    @endphp
    <x-page-header title="Products" subtitle="{{ $products->total() }} products">
        <x-export-buttons />
        @can('shipments.view')<a href="{{ route('shipments.index') }}" class="btn btn-secondary">Upcoming shipments</a>@endcan
        @can('products.manage')
            <a href="{{ route('labels.index') }}" class="btn btn-secondary">Print labels</a>
            <a href="{{ route('products.create') }}" class="btn btn-primary">New product</a>
        @endcan
    </x-page-header>

    {{-- Filters: Category → Brand --}}
    <form method="GET" class="card card-body mb-3 flex flex-wrap items-end gap-3"
          x-data="{ category: @js(request('category_id', '')), brand: @js(request('brand_id', '')), brands: @js($brands) }">
        <div class="min-w-56 flex-1">
            <label class="label">Search</label>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Product code, part no, name, vehicle..." class="input">
        </div>
        <div class="w-44">
            <label class="label">Category</label>
            <select name="category_id" x-model="category" @change="brand = ''" class="input">
                <option value="">All categories</option>
                @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div class="w-44">
            <label class="label">Brand</label>
            <select name="brand_id" x-model="brand" class="input">
                <option value="">All brands</option>
                <template x-for="b in brands.filter(b => !category || b.category_id == category)" :key="b.id">
                    <option :value="b.id" x-text="b.name" :selected="b.id == brand"></option>
                </template>
            </select>
        </div>
        <x-select label="Made in" name="made_in" :options="$origins->combine($origins)" :value="request('made_in')" placeholder="Any" class="w-36" />
        <x-select label="Stock" name="stock" :value="request('stock')" placeholder="All" class="w-44"
                  :options="['ok' => 'In stock (green)', 'low' => 'Low stock (yellow)', 'out' => 'Out of stock (red)', 'incoming' => 'On upcoming shipment']" />
        <button class="btn btn-secondary">Filter</button>
        @if (request()->hasAny(['q', 'category_id', 'brand_id', 'made_in', 'stock']))<a href="{{ route('products.index') }}" class="btn btn-secondary">Clear</a>@endif
    </form>

    <div class="mb-3 flex flex-wrap items-center gap-3 text-xs text-slate-600">
        <span class="font-medium">Stock:</span>
        <x-stock-badge :qty="0" :show-qty="false" /> none
        <x-stock-badge :qty="$low" :show-qty="false" /> {{ qty($low) }} or fewer
        <x-stock-badge :qty="$low + 1" :show-qty="false" /> more than {{ qty($low) }}
        <span class="ml-3 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 ring-1 ring-emerald-200">⛴ Ship</span> on an upcoming shipment – not in stock yet
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead>
            <tr>
                <th class="w-14"></th><th>Product code / part no</th><th>Description</th><th>Category › Brand</th><th>Made in</th>
                @if ($seeCost)<th class="num">Cost</th>@endif
                <th class="num">Retail</th><th class="num">Wholesale</th><th class="num">Tax %</th><th class="num">Disc %</th>
                <th class="text-center">Stock</th><th class="bg-emerald-50 text-center text-emerald-800">Ship</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($products as $p)
                <tr>
                    <td>
                        @if ($p->imageUrl())
                            <a href="{{ route('products.show', $p) }}"><img src="{{ $p->imageUrl() }}" alt="" loading="lazy" class="h-11 w-11 rounded border border-slate-200 object-cover"></a>
                        @else
                            <div class="flex h-11 w-11 items-center justify-center rounded border border-dashed border-slate-200 text-[10px] text-slate-300">No pic</div>
                        @endif
                    </td>
                    <td><a href="{{ route('products.show', $p) }}" class="link font-medium">{{ $p->sku }}</a>
                        @if ($p->part_number)<div class="font-mono text-xs text-slate-500">{{ $p->part_number }}</div>@endif</td>
                    <td>{{ $p->name }}@if ($p->location)<div class="text-xs text-slate-400">Bin {{ $p->location }}</div>@endif</td>
                    <td class="text-sm">{{ $p->brand?->category?->name ?? $p->category?->name }}@if ($p->brand)<span class="text-slate-400"> › </span>{{ $p->brand->name }}@endif</td>
                    <td>{{ $p->made_in }}</td>
                    @if ($seeCost)<td class="num">{{ money($p->cost_price, false) }}</td>@endif
                    <td class="num">{{ money($p->selling_price, false) }}</td>
                    <td class="num">{{ $p->wholesale_price > 0 ? money($p->wholesale_price, false) : '-' }}</td>
                    <td class="num">{{ $p->tax_rate > 0 ? qty($p->tax_rate) : '-' }}</td>
                    <td class="num">{{ $p->discount_percent > 0 ? qty($p->discount_percent) : '-' }}</td>
                    <td class="text-center"><x-stock-badge :qty="$p->total_stock ?? 0" /></td>
                    <td class="bg-emerald-50/40 text-center"><x-ship-badge :qty="$p->incoming_qty ?? 0" :date="$nextDates[$p->id] ?? null" /></td>
                </tr>
            @empty
                <tr><td colspan="12" class="py-6 text-center text-slate-500">No products found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
</x-layouts.app>
