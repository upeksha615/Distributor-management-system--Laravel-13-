<x-layouts.app :title="$product->sku">
    @php $canEdit = auth()->user()->can('products.manage'); $seeCost = auth()->user()->can('products.view_cost'); $seeQty = auth()->user()->can('stock.view_qty'); @endphp
    <x-page-header :title="$product->sku.' - '.$product->name" :subtitle="collect([$product->part_number ? 'OEM '.$product->part_number : null, $product->brand?->name, $product->category?->name, $product->location ? 'Bin '.$product->location : null])->filter()->join(' · ')">
        @if ($canEdit)
            <a href="{{ route('products.edit', $product) }}" class="btn btn-secondary">Edit</a>
        @endif
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card card-body">
            @if ($product->imageUrl())
                <a href="{{ $product->imageUrl() }}" target="_blank"><img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="mb-4 max-h-56 w-full rounded border border-slate-200 object-contain"></a>
            @endif
            <dl class="grid grid-cols-2 gap-y-2.5 text-sm">
                <dt class="text-slate-500">Category › Brand</dt><dd>{{ $product->brand?->category?->name ?? $product->category?->name ?? '-' }} › {{ $product->brand?->name ?? '-' }}</dd>
                <dt class="text-slate-500">Made in</dt><dd>{{ $product->made_in ?: '-' }}</dd>
                <dt class="text-slate-500">Tax %</dt><dd>{{ qty($product->tax_rate) }}%</dd>
                <dt class="text-slate-500">Standard discount</dt><dd>{{ $product->discount_percent > 0 ? qty($product->discount_percent).'%' : '-' }}</dd>
                <dt class="text-slate-500">Stock</dt><dd><x-stock-badge :qty="$product->stockLevels->sum('quantity')" /></dd>
                <dt class="text-slate-500">Status</dt><dd><x-status :status="$product->is_active ? 'active' : 'inactive'" /></dd>
                <dt class="text-slate-500">Barcode</dt><dd>{{ $product->barcode ?: '-' }}</dd>
                <dt class="text-slate-500">Unit</dt><dd>{{ $product->unit }}</dd>
                @if ($seeCost)<dt class="text-slate-500">Cost price</dt><dd>{{ money($product->cost_price) }}</dd>@endif
                <dt class="text-slate-500">Retail price</dt><dd>{{ money($product->selling_price) }}</dd>
                <dt class="text-slate-500">Wholesale price</dt><dd>{{ $product->wholesale_price > 0 ? money($product->wholesale_price) : 'Same as retail' }}</dd>
                <dt class="text-slate-500">Minimum price</dt><dd>{{ $product->min_price > 0 ? money($product->min_price) : '-' }}</dd>
                @if ($seeCost)
                <dt class="text-slate-500">Retail margin</dt>
                <dd>{{ $product->selling_price > 0 ? number_format(($product->selling_price - $product->cost_price) / $product->selling_price * 100, 1).'%' : '-' }}</dd>
                @endif
                @if ($seeCost && $product->wholesale_price > 0)
                    <dt class="text-slate-500">Wholesale margin</dt>
                    <dd>{{ number_format(($product->wholesale_price - $product->cost_price) / $product->wholesale_price * 100, 1) }}%</dd>
                @endif
                <dt class="text-slate-500">Reorder level</dt><dd>{{ $product->reorder_level ?: '-' }}</dd>
            </dl>
            @if ($product->description)<p class="mt-4 text-sm text-slate-600">{{ $product->description }}</p>@endif
        </div>

        <div class="card lg:col-span-2">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Stock by warehouse</h2>
            <table class="table">
                <thead><tr><th>Warehouse</th><th class="num">{{ $seeQty ? 'Quantity' : 'Availability' }}</th></tr></thead>
                <tbody>
                @forelse ($product->stockLevels as $level)
                    <tr><td>{{ $level->warehouse->name }}</td><td class="num">@if ($seeQty){{ qty($level->quantity) }} {{ $product->unit }}@else<x-stock-badge :qty="$level->quantity" />@endif</td></tr>
                @empty
                    <tr><td colspan="2" class="py-4 text-center text-slate-500">No stock recorded.</td></tr>
                @endforelse
                <tr class="font-semibold"><td>Total</td><td class="num">@if ($seeQty){{ qty($product->stockLevels->sum('quantity')) }} {{ $product->unit }}@else<x-stock-badge :qty="$product->stockLevels->sum('quantity')" />@endif</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    @php
        $incoming = $product->shipmentItems()->with('shipment.warehouse')->whereColumn('received_quantity', '<', 'quantity')
            ->whereHas('shipment', fn ($q) => $q->whereIn('status', ['pending', 'partial']))->get();
    @endphp
    @if ($incoming->isNotEmpty() && auth()->user()->can('shipments.view'))
        <div class="card mt-6 border-emerald-200">
            <h2 class="border-b border-emerald-200 bg-emerald-50 px-5 py-3 font-semibold text-emerald-800">On upcoming shipments (not in stock yet)</h2>
            <table class="table">
                <thead><tr><th>Shipment</th><th>Reference</th><th>To warehouse</th><th>Expected</th><th class="num">{{ $seeQty ? 'Quantity coming' : 'Coming' }}</th></tr></thead>
                <tbody>
                @foreach ($incoming as $i)
                    <tr>
                        <td><a href="{{ route('shipments.show', $i->shipment) }}" class="link">{{ $i->shipment->number }}</a></td>
                        <td>{{ $i->shipment->reference }}</td>
                        <td>{{ $i->shipment->warehouse->name }}</td>
                        <td>{{ $i->shipment->expected_date?->format('d/m/Y') ?? '-' }}</td>
                        <td class="num"><x-ship-badge :qty="$i->outstanding()" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{-- Cross references --}}
        <div class="card">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Part numbers / cross references</h2>
            <table class="table">
                <thead><tr><th>Number</th><th>Type</th><th>Brand</th>@if ($canEdit)<th></th>@endif</tr></thead>
                <tbody>
                @if ($product->part_number)
                    <tr><td class="font-medium">{{ $product->part_number }}</td><td>OEM (main)</td><td></td>@if ($canEdit)<td></td>@endif</tr>
                @endif
                @foreach ($product->partNumbers as $pn)
                    <tr>
                        <td class="font-medium">{{ $pn->number }}</td>
                        <td>{{ \App\Models\ProductPartNumber::TYPES[$pn->type] ?? $pn->type }}</td>
                        <td>{{ $pn->brand }}</td>
                        @if ($canEdit)
                            <td class="text-right">
                                <form method="POST" action="{{ route('products.part-numbers.destroy', [$product, $pn]) }}">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Remove</button></form>
                            </td>
                        @endif
                    </tr>
                @endforeach
                @if (! $product->part_number && $product->partNumbers->isEmpty())
                    <tr><td colspan="4" class="py-4 text-center text-slate-500">No part numbers yet.</td></tr>
                @endif
                </tbody>
            </table>
            @if ($canEdit)
                <form method="POST" action="{{ route('products.part-numbers.store', $product) }}" class="flex flex-wrap items-end gap-2 border-t border-slate-200 p-4">
                    @csrf
                    <div class="min-w-36 flex-1"><label class="label">Number</label><input name="number" class="input" required></div>
                    <div class="w-32"><label class="label">Type</label>
                        <select name="type" class="input">@foreach (\App\Models\ProductPartNumber::TYPES as $k => $v)<option value="{{ $k }}" @selected($k === 'alternate')>{{ $v }}</option>@endforeach</select></div>
                    <div class="w-32"><label class="label">Brand</label><input name="brand" class="input" placeholder="e.g. Denso"></div>
                    <button class="btn btn-primary">Add</button>
                </form>
            @endif
            <p class="px-5 pb-4 text-xs text-slate-500">All numbers here are searchable in the POS, invoices and product list, with or without dashes.</p>
        </div>

        {{-- Vehicle fitment --}}
        <div class="card">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Fits vehicles</h2>
            <table class="table">
                <thead><tr><th>Vehicle</th><th>Notes</th>@if ($canEdit)<th></th>@endif</tr></thead>
                <tbody>
                @forelse ($product->fitments as $f)
                    <tr>
                        <td class="font-medium">{{ $f->label() }}</td>
                        <td class="text-slate-500">{{ $f->notes }}</td>
                        @if ($canEdit)
                            <td class="text-right">
                                <form method="POST" action="{{ route('products.fitments.destroy', [$product, $f]) }}">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Remove</button></form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-4 text-center text-slate-500">No vehicles yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            @if ($canEdit)
                <form method="POST" action="{{ route('products.fitments.store', $product) }}" class="grid grid-cols-2 gap-2 border-t border-slate-200 p-4 sm:grid-cols-6">
                    @csrf
                    <div class="sm:col-span-1"><label class="label">Make</label><input name="make" class="input" required placeholder="Toyota"></div>
                    <div class="sm:col-span-2"><label class="label">Model</label><input name="model" class="input" placeholder="Axio NZE161"></div>
                    <div><label class="label">From</label><input name="year_from" type="number" class="input" placeholder="2012"></div>
                    <div><label class="label">To</label><input name="year_to" type="number" class="input" placeholder="2018"></div>
                    <div><label class="label">Engine</label><input name="engine" class="input" placeholder="1NZ-FE"></div>
                    <div class="col-span-2 sm:col-span-5"><input name="notes" class="input" placeholder="Notes (optional), e.g. front axle, with ABS"></div>
                    <button class="btn btn-primary">Add</button>
                </form>
            @endif
        </div>
    </div>

    @if ($seeQty)
        <div class="card mt-6">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Stock movements</h2>
            @include('stock._movements-table', ['movements' => $movements, 'showProduct' => false])
        </div>
        <div class="mt-4">{{ $movements->links() }}</div>
    @endif
</x-layouts.app>
