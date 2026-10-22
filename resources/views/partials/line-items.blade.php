{{--
    Product line editor.
    $mode: 'sale' (qty, price, discount %, totals), 'purchase' (qty, unit cost, total) or 'stock' (qty only)
    $items: existing lines; $warehouseField: name of the warehouse <select> to read stock from
--}}
@php
    $mode = $mode ?? 'sale';
    $config = [
        'items' => old('items') ? collect(old('items'))->values() : ($items ?? []),
        'priceField' => $mode === 'purchase' ? 'cost_price' : 'selling_price',
        'withDiscount' => $mode === 'sale',
        'discount' => old('discount', $discount ?? 0),
        'taxPercent' => old('tax_percent', $taxPercent ?? 0),
        'lookupUrl' => route('products.lookup'),
        'warehouseId' => old($warehouseField ?? 'warehouse_id', $warehouseId ?? ''),
        // customer id => 'wholesale' | 'retail', so new lines get the right price for the selected customer
        'customerLevels' => isset($customers) ? $customers->pluck('price_level', 'id') : (object) [],
        'priceLevel' => isset($customers) ? ($customers->firstWhere('id', old('customer_id', $selectedCustomer ?? null))?->price_level ?? 'retail') : 'retail',
    ];
@endphp
<div x-data="lineItems(@js($config))"
     @warehouse-changed.window="warehouseId = $event.detail"
     @customer-changed.window="priceLevel = customerLevels[$event.detail] || 'retail'"
     class="card">
    <div class="border-b border-slate-200 p-4">
        <label class="label">{{ $qtyLabel ?? 'Add product' }}</label>
        <div class="relative max-w-xl" @click.outside="open = false">
            <input x-ref="search" type="search" x-model="query" @input.debounce.250ms="search()"
                   @keydown.down.prevent="highlighted = Math.min(highlighted + 1, results.length - 1)"
                   @keydown.up.prevent="highlighted = Math.max(highlighted - 1, 0)"
                   @keydown.enter.prevent="pickHighlighted()"
                   placeholder="Type product code, part no or name..." class="input" autocomplete="off">
            <div x-show="open" x-cloak class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-md border border-slate-200 bg-white shadow-lg">
                <template x-for="(p, i) in results" :key="p.id">
                    <button type="button" @click="add(p)" @mouseenter="highlighted = i"
                            :class="highlighted === i ? 'bg-indigo-50' : ''"
                            class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm">
                        <span><span class="font-medium" x-text="p.sku"></span> - <span x-text="p.name"></span></span>
                        <span class="flex shrink-0 items-center gap-2 whitespace-nowrap text-xs text-slate-500">
                            <span class="rounded-full px-2 py-0.5 font-semibold ring-1" :class="stockClass(p.stock_level)" x-text="stockText(p)"></span>
                            <span x-show="p.incoming" class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700 ring-1 ring-emerald-200" title="Upcoming shipment" x-text="'⛴ ' + (p.incoming === true ? 'Coming' : p.incoming)"></span>
                            @if ($mode !== 'stock')<span x-text="money(priceOf(p))"></span>@endif
                        </span>
                    </button>
                </template>
                <div x-show="results.length === 0" class="px-3 py-2 text-sm text-slate-500">No products found.</div>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="table">
            <thead>
            <tr>
                <th>Product</th>
                <th class="w-28 num">{{ $quantityHeader ?? 'Qty' }}</th>
                @if ($mode === 'purchase')
                    <th class="w-36 num">Unit cost</th>
                @elseif ($mode === 'sale')
                    <th class="w-36 num">Unit price</th>
                    <th class="w-24 num">Disc %</th>
                    <th class="w-24 num">Tax %</th>
                @endif
                @if ($mode !== 'stock')<th class="w-36 num">Total</th>@endif
                <th class="w-10"></th>
            </tr>
            </thead>
            <tbody>
            <template x-for="(item, index) in items" :key="index">
                <tr>
                    <td>
                        <input type="hidden" :name="`items[${index}][product_id]`" :value="item.product_id">
                        <input type="hidden" :name="`items[${index}][label]`" :value="item.label">
                        <div x-text="item.label"></div>
                        <div x-show="item.stock_level" class="mt-0.5 flex gap-2 text-xs">
                            <span class="rounded-full px-2 py-0.5 font-semibold ring-1" :class="stockClass(item.stock_level)" x-text="item.stock === null || item.stock === undefined ? stockText(item) : 'Stock ' + item.stock"></span>
                            <span x-show="item.incoming" class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700 ring-1 ring-emerald-200" x-text="'⛴ ' + (item.incoming === true ? 'Coming' : item.incoming + ' coming')"></span>
                        </div>
                    </td>
                    <td><input type="number" step="0.01" {{ $mode === 'stock' && ($allowNegativeQty ?? false) ? '' : 'min=0.01' }} :name="`items[${index}][quantity]`" x-model="item.quantity" class="input text-right" required></td>
                    @if ($mode === 'purchase')
                        <td><input type="number" step="0.01" min="0" :name="`items[${index}][unit_cost]`" x-model="item.unit_cost" class="input text-right" required></td>
                    @elseif ($mode === 'sale')
                        <td><input type="number" step="0.01" min="0" :name="`items[${index}][unit_price]`" x-model="item.unit_price" class="input text-right" required></td>
                        <td><input type="number" step="0.01" min="0" max="100" :name="`items[${index}][discount_percent]`" x-model="item.discount_percent" class="input text-right"></td>
                        <td><input type="number" step="0.01" min="0" max="100" :name="`items[${index}][tax_percent]`" x-model="item.tax_percent" class="input text-right"></td>
                    @endif
                    @if ($mode !== 'stock')<td class="num pt-4" x-text="money(lineTotal(item))"></td>@endif
                    <td><button type="button" @click="remove(index)" class="btn btn-danger btn-sm" title="Remove">✕</button></td>
                </tr>
            </template>
            <tr x-show="items.length === 0"><td colspan="7" class="py-6 text-center text-slate-500">No products added yet. Search above to add lines.</td></tr>
            </tbody>
        </table>
    </div>

    @if ($mode !== 'stock')
        <div class="flex justify-end border-t border-slate-200 p-4">
            <dl class="w-full max-w-xs space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="tabular-nums" x-text="money(subtotal)"></dd></div>
                @if ($mode === 'sale')
                    <div class="flex items-center justify-between gap-3"><dt class="text-slate-500">Discount (amount)</dt>
                        <dd><input type="number" step="0.01" min="0" name="discount" x-model.number="discount" class="input w-32 text-right"></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Tax (per product)</dt><dd class="tabular-nums" x-text="money(tax)"></dd></div>
                @endif
                <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold">
                    <dt>Total</dt><dd class="tabular-nums">{{ config('distributor.currency') }} <span x-text="money(total)"></span></dd>
                </div>
            </dl>
        </div>
    @endif
</div>
