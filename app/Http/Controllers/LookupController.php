<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Catalogue structure: Categories, and Brands that belong to a category. */
class LookupController extends Controller
{
    private const TYPES = [
        'categories' => ['model' => Category::class, 'title' => 'Categories', 'singular' => 'Category'],
        'brands' => ['model' => Brand::class, 'title' => 'Brands', 'singular' => 'Brand'],
    ];

    public function index(Request $request, string $type)
    {
        $meta = self::TYPES[$type];
        $rows = $type === 'brands'
            ? Brand::with('category')->withCount('products')
                ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
                ->leftJoin('categories', 'categories.id', '=', 'brands.category_id')
                ->orderByRaw('categories.name IS NULL, categories.name')->orderBy('brands.name')->select('brands.*')->get()
            : Category::withCount(['products', 'brands'])->orderBy('name')->get();

        return view('lookups.index', [
            'type' => $type, 'meta' => $meta, 'rows' => $rows,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $type)
    {
        $meta = self::TYPES[$type];
        $meta['model']::create($this->validated($request, $type));

        return back()->with('success', "{$meta['singular']} added.");
    }

    public function update(Request $request, string $type, int $id)
    {
        $meta = self::TYPES[$type];
        $row = $meta['model']::findOrFail($id);
        $data = $this->validated($request, $type, $id);

        DB::transaction(function () use ($row, $data, $type) {
            $row->update($data);
            // Moving a brand to another category moves its products with it.
            if ($type === 'brands') {
                Product::where('brand_id', $row->id)->update(['category_id' => $row->category_id]);
            }
        });

        return back()->with('success', "{$meta['singular']} updated.");
    }

    public function destroy(string $type, int $id)
    {
        $meta = self::TYPES[$type];
        $row = $meta['model']::withCount('products')->findOrFail($id);

        if ($row->products_count > 0) {
            return back()->with('error', "This {$meta['singular']} is used by {$row->products_count} product(s) and cannot be deleted.");
        }
        if ($type === 'categories' && $row->brands()->exists()) {
            return back()->with('error', 'Move or delete the brands in this category first.');
        }

        $row->delete();

        return back()->with('success', "{$meta['singular']} deleted.");
    }

    private function validated(Request $request, string $type, ?int $id = null): array
    {
        if ($type === 'brands') {
            return $request->validate([
                'category_id' => ['required', 'exists:categories,id'],
                'name' => ['required', 'max:255', Rule::unique('brands', 'name')->where('category_id', $request->category_id)->ignore($id)],
            ], ['name.unique' => 'This brand already exists in that category.']);
        }

        return $request->validate(['name' => ['required', 'max:255', Rule::unique('categories', 'name')->ignore($id)]]);
    }
}
