<x-layouts.app :title="$product->exists ? 'Edit product' : 'New product'">
    <x-page-header :title="$product->exists ? 'Edit '.$product->sku : 'New product'">
        <a href="{{ $product->exists ? route('products.show', $product) : route('products.index') }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" class="max-w-4xl space-y-4" enctype="multipart/form-data">
        @csrf
        @if ($product->exists) @method('PUT') @endif

        <div class="card card-body space-y-4">
            <h2 class="font-semibold">Identification</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field label="Product code" name="sku" :value="$product->sku" required help="Unique. Stock and shipments are matched on this code." />
                <x-field label="Part number (OEM)" name="part_number" :value="$product->part_number" help="Manufacturer number, e.g. 04465-12592. Add more numbers on the product page." />
                <x-field label="Barcode" name="barcode" :value="$product->barcode" help="Scan it in. Leave blank to print labels with the SKU." />
                <x-field label="Description" name="name" :value="$product->name" required class="sm:col-span-2" />
                <x-field label="Rack / bin location" name="location" :value="$product->location" help="e.g. A-03-2" />
                <div class="contents" x-data="{ category: @js(old('category_id', $product->brand?->category_id ?? $product->category_id ?? '')), brand: @js(old('brand_id', $product->brand_id ?? '')),
                        brands: @js($brands->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'category_id' => $b->category_id])) }">
                    <div>
                        <label class="label">Category</label>
                        <select name="category_id" x-model="category" @change="brand = ''" class="input">
                            <option value="">-</option>
                            @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Brand</label>
                        <select name="brand_id" x-model="brand" class="input">
                            <option value="">-</option>
                            <template x-for="b in brands.filter(b => !category || b.category_id == category)" :key="b.id">
                                <option :value="b.id" x-text="b.name" :selected="b.id == brand"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Brands are listed under their category (Setup → Brands).</p>
                    </div>
                </div>
                <x-field label="Made in" name="made_in" :value="$product->made_in" list="origins" help="Country of origin" />
                <datalist id="origins">@foreach (\App\Models\Product::whereNotNull('made_in')->distinct()->orderBy('made_in')->pluck('made_in') as $o)<option value="{{ $o }}">@endforeach</datalist>
                <x-field label="Unit" name="unit" :value="$product->unit" required help="pcs, set, pair, box, litre" />
            </div>
        </div>

        <div class="card card-body space-y-4">
            <h2 class="font-semibold">Prices</h2>
            <div class="grid gap-4 sm:grid-cols-4">
                <x-field label="Cost price" name="cost_price" type="number" step="0.01" min="0" :value="$product->cost_price ?? 0" required help="Updated automatically from goods received." />
                <x-field label="Retail price" name="selling_price" type="number" step="0.01" min="0" :value="$product->selling_price ?? 0" required help="Walk-in and retail customers." />
                <x-field label="Wholesale / dealer price" name="wholesale_price" type="number" step="0.01" min="0" :value="$product->wholesale_price ?? 0" help="Dealers. 0 = use retail price." />
                <x-field label="Minimum price" name="min_price" type="number" step="0.01" min="0" :value="$product->min_price ?? 0" help="Lowest selling price. 0 = no limit." />
                <x-field label="Tax %" name="tax_rate" type="number" step="0.01" min="0" max="100" :value="$product->tax_rate ?? 0" help="VAT / tax charged on this product. Confirm rates with your accountant." />
                <x-field label="Standard discount %" name="discount_percent" type="number" step="0.01" min="0" max="100" :value="$product->discount_percent ?? 0" help="Pre-filled on orders and invoices; can be changed per line." />
            </div>
        </div>

        <div class="card card-body space-y-3">
            <h2 class="font-semibold">Picture</h2>
            <div class="flex flex-wrap items-center gap-4">
                @if ($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="" class="h-24 w-24 rounded border border-slate-200 object-cover">
                    <x-checkbox label="Remove picture" name="remove_image" />
                @endif
                <div>
                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="block text-sm file:mr-3 file:rounded-md file:border file:border-slate-300 file:bg-white file:px-3 file:py-1.5">
                    <p class="mt-1 text-xs text-slate-500">JPG, PNG or WEBP up to 2 MB.</p>
                    @error('image')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="card card-body space-y-4">
            <h2 class="font-semibold">Stock control</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field label="Reorder level" name="reorder_level" type="number" min="0" :value="$product->reorder_level ?? 0" required help="Flag as low stock at or below this quantity. 0 = off." />
            </div>
            <x-field label="Notes" name="description" type="textarea" :value="$product->description" />
            <x-checkbox label="Active (can be sold and purchased)" name="is_active" :checked="$product->is_active" />
        </div>

        @unless ($product->exists)
            <p class="rounded-md bg-slate-50 p-3 text-sm text-slate-600">After saving you can add alternate part numbers and the vehicles this part fits. Load opening stock with <strong>Adjust stock</strong> or <strong>Import data</strong>.</p>
        @endunless

        <button class="btn btn-primary">Save product</button>
    </form>
</x-layouts.app>
