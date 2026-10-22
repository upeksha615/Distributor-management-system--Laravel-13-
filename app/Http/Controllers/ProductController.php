<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\ProductPartNumber;
use App\Models\Warehouse;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /** Product list: Category → Brand filters, stock colours and upcoming shipments (the reps' stock check). */
    public function index(Request $request)
    {
        $low = (float) config('distributor.stock_low_threshold', 10);
        $stockSql = '(SELECT COALESCE(SUM(quantity), 0) FROM stock_levels WHERE stock_levels.product_id = products.id)';

        $products = Product::with(['brand.category'])
            ->withTotalStock()
            ->withIncoming()
            ->search($request->q)
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->brand_id, fn ($q, $id) => $q->where('brand_id', $id))
            ->when($request->made_in, fn ($q, $m) => $q->where('made_in', $m))
            ->when($request->stock === 'ok', fn ($q) => $q->whereRaw("$stockSql > ?", [$low]))
            ->when($request->stock === 'low', fn ($q) => $q->whereRaw("$stockSql > 0 AND $stockSql <= ?", [$low]))
            ->when($request->stock === 'out', fn ($q) => $q->whereRaw("$stockSql <= 0"))
            ->when($request->stock === 'incoming', fn ($q) => $q->whereHas('shipmentItems', fn ($i) => $i->whereColumn('received_quantity', '<', 'quantity')
                ->whereHas('shipment', fn ($sh) => $sh->whereIn('status', ['pending', 'partial']))))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false), fn ($q) => $q->where('is_active', true))
            ->orderBy('name');

        if ($format = $this->exportFormat($request)) {
            $seeCost = $request->user()->can('products.view_cost');
            $seeQty = $request->user()->can('stock.view_qty');
            $columns = ['Product code' => 'text', 'Part no' => 'text', 'Barcode' => 'text', 'Description' => 'text', 'Category' => 'text', 'Brand' => 'text',
                'Made in' => 'text', 'Unit' => 'text', 'Bin' => 'text']
                + ($seeCost ? ['Cost price' => 'money'] : [])
                + ['Retail price' => 'money', 'Wholesale price' => 'money', 'Tax %' => 'qty', 'Discount %' => 'qty']
                + ($seeQty ? ['Stock' => 'qty', 'Upcoming shipment' => 'qty'] : ['Availability' => 'text', 'Upcoming shipment' => 'text'])
                + ['Active' => 'text'];

            return Exporter::download($format, 'products', 'Products', $columns, $products->get()->map(fn ($p) => array_merge(
                [$p->sku, $p->part_number, $p->barcode, $p->name, $p->brand?->category?->name ?? $p->category?->name, $p->brand?->name, $p->made_in, $p->unit, $p->location],
                $seeCost ? [$p->cost_price] : [],
                [$p->selling_price, $p->wholesale_price, $p->tax_rate, $p->discount_percent],
                $seeQty ? [$p->total_stock ?? 0, $p->incoming_qty ?? 0]
                    : [['out' => 'Out of stock', 'low' => 'Low', 'ok' => 'In stock'][Product::stockLevel((float) $p->total_stock)], (float) $p->incoming_qty > 0 ? 'Coming' : ''],
                [$p->is_active ? 'Yes' : 'No'],
            )), $this->filterSummary($request));
        }

        return view('products.index', [
            'products' => $products->paginate(30)->withQueryString(),
            'categories' => Category::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(['id', 'name', 'category_id']),
            'origins' => Product::whereNotNull('made_in')->distinct()->orderBy('made_in')->pluck('made_in'),
            'nextDates' => \App\Models\ShipmentItem::query()
                ->join('shipments', 'shipments.id', '=', 'shipment_items.shipment_id')
                ->whereIn('shipments.status', ['pending', 'partial'])->whereColumn('received_quantity', '<', 'quantity')
                ->groupBy('product_id')->selectRaw('product_id, MIN(shipments.expected_date) as eta')->pluck('eta', 'product_id'),
        ]);
    }
    /** JSON search used by the line-item pickers on orders and invoices. */
    public function lookup(Request $request)
    {
        $warehouseId = $request->integer('warehouse_id');
        $seeCost = $request->user()->can('products.view_cost');
        $seeQty = $request->user()->can('stock.view_qty');

        return Product::where('is_active', true)
            ->search($request->q)
            ->with(['stockLevels', 'brand'])
            ->withIncoming()
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'unit' => $p->unit,
                'part_number' => $p->part_number,
                'cost_price' => $seeCost ? (float) $p->cost_price : null,
                'selling_price' => (float) $p->selling_price,
                'wholesale_price' => (float) $p->wholesale_price,
                // Reps get the colour and a yes/no for shipments, never the quantities.
                'stock' => $seeQty ? ($stock = $warehouseId ? $p->stockIn($warehouseId) : (float) $p->stockLevels->sum('quantity')) : null,
                'stock_level' => Product::stockLevel($seeQty ? $stock : ($warehouseId ? $p->stockIn($warehouseId) : (float) $p->stockLevels->sum('quantity'))),
                'incoming' => $seeQty ? (float) $p->incoming_qty : ((float) $p->incoming_qty > 0),
                'tax_rate' => (float) $p->tax_rate,
                'discount_percent' => (float) $p->discount_percent,
                'brand' => $p->brand?->name,
                'made_in' => $p->made_in,
            ]);
    }

    public function show(Product $product)
    {
        $product->load(['brand.category', 'category', 'stockLevels.warehouse', 'partNumbers', 'fitments']);
        $movements = $product->movements()->with(['warehouse', 'user', 'reference'])->latest('id')->paginate(20);

        return view('products.show', compact('product', 'movements'));
    }

    public function create()
    {
        return view('products.form', $this->formData(new Product(['unit' => 'pcs', 'is_active' => true])));
    }

    public function store(Request $request)
    {
        $product = Product::create($this->validated($request));
        $this->saveImage($request, $product);

        return redirect()->route('products.show', $product)->with('success', 'Product created.');
    }

    public function edit(Product $product)
    {
        return view('products.form', $this->formData($product));
    }

    public function update(Request $request, Product $product)
    {
        $product->fill($this->validated($request, $product));

        // Price changes are audited: they directly affect margins and what cashiers can charge.
        $priceFields = ['cost_price', 'selling_price', 'wholesale_price', 'min_price'];
        if ($product->isDirty($priceFields)) {
            $changes = collect($priceFields)->filter(fn ($f) => $product->isDirty($f))
                ->map(fn ($f) => str_replace('_', ' ', $f).' '.number_format((float) $product->getOriginal($f), 2).' → '.number_format((float) $product->$f, 2));
            AuditLog::record('product.price', $product, "{$product->sku}: ".$changes->join(', '));
        }
        $product->save();
        $this->saveImage($request, $product);

        return redirect()->route('products.show', $product)->with('success', 'Product updated.');
    }

    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'brands' => Brand::with('category')->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'sku' => ['required', 'max:50', Rule::unique('products')->ignore($product)],
            'name' => ['required', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'unit' => ['required', 'max:20'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'part_number' => ['nullable', 'max:100'],
            'barcode' => ['nullable', 'max:100', Rule::unique('products')->ignore($product)],
            'location' => ['nullable', 'max:50'],
            'description' => ['nullable', 'max:2000'],
            'made_in' => ['nullable', 'max:60'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]) + ['is_active' => $request->boolean('is_active')];

        unset($data['image']);
        $data['tax_rate'] = (float) ($data['tax_rate'] ?? 0);
        $data['discount_percent'] = (float) ($data['discount_percent'] ?? 0);
        // The category follows the brand (Category → Brand → Product).
        if (! empty($data['brand_id']) && ($brandCategory = Brand::whereKey($data['brand_id'])->value('category_id'))) {
            $data['category_id'] = $brandCategory;
        }

        return $data;
    }

    /** Product pictures are stored privately and served through products.image (no public symlink needed). */
    private function saveImage(Request $request, Product $product): void
    {
        if ($request->boolean('remove_image') && $product->image_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($product->image_path);
            $product->update(['image_path' => null]);
        }
        if ($request->hasFile('image')) {
            if ($product->image_path) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($product->image_path);
            }
            $product->update(['image_path' => $request->file('image')->store('product-images', 'local')]);
        }
    }

    public function image(Product $product)
    {
        abort_unless($product->image_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($product->image_path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($product->image_path, null, [
            'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // ---- Cross-reference part numbers and vehicle fitment (managed on the product page) ----

    public function addPartNumber(Request $request, Product $product)
    {
        $data = $request->validate([
            'number' => ['required', 'max:100'],
            'type' => ['required', Rule::in(array_keys(ProductPartNumber::TYPES))],
            'brand' => ['nullable', 'max:100'],
        ]);
        $product->partNumbers()->create($data);

        return back()->with('success', 'Part number added.');
    }

    public function deletePartNumber(Product $product, ProductPartNumber $partNumber)
    {
        abort_unless($partNumber->product_id === $product->id, 404);
        $partNumber->delete();

        return back()->with('success', 'Part number removed.');
    }

    public function addFitment(Request $request, Product $product)
    {
        $data = $request->validate([
            'make' => ['required', 'max:50'],
            'model' => ['nullable', 'max:100'],
            'year_from' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'year_to' => ['nullable', 'integer', 'min:1950', 'max:2100', 'gte:year_from'],
            'engine' => ['nullable', 'max:50'],
            'notes' => ['nullable', 'max:255'],
        ]);
        $product->fitments()->create($data);

        return back()->with('success', 'Vehicle added.');
    }

    public function deleteFitment(Product $product, ProductFitment $fitment)
    {
        abort_unless($fitment->product_id === $product->id, 404);
        $fitment->delete();

        return back()->with('success', 'Vehicle removed.');
    }

    /** Barcode label sheet for selected products. */
    public function labelsForm()
    {
        return view('products.labels');
    }

    public function labelsPrint(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'show_price' => ['boolean'],
        ]);
        $products = Product::with('brand')->whereIn('id', collect($data['items'])->pluck('product_id'))->get()->keyBy('id');
        $labels = collect($data['items'])->flatMap(fn ($i) => array_fill(0, (int) $i['quantity'], $products[$i['product_id']]));

        return view('products.labels-print', ['labels' => $labels, 'showPrice' => $request->boolean('show_price')]);
    }
}
