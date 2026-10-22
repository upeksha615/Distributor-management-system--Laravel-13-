<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request)
    {
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        $products = Product::with('stockLevels')
            ->where('is_active', true)
            ->search($request->q)
            ->orderBy('name');

        if ($format = $this->exportFormat($request)) {
            $columns = ['SKU' => 'text', 'Name' => 'text'] + $warehouses->mapWithKeys(fn ($w) => [$w->name => 'qty'])->all() + ['Total' => 'qty'];

            return Exporter::download($format, 'stock-levels', 'Stock levels', $columns, $products->get()->map(fn ($p) => array_merge(
                [$p->sku, $p->name],
                $warehouses->map(fn ($w) => $p->stockIn($w->id))->all(),
                [$p->stockLevels->sum('quantity')],
            )), $this->filterSummary($request));
        }

        $products = $products->paginate(30)->withQueryString();

        return view('stock.index', compact('products', 'warehouses'));
    }

    public function movements(Request $request)
    {
        $movements = StockMovement::with(['product', 'warehouse', 'user', 'reference'])
            ->when($request->warehouse_id, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->q, fn ($q, $term) => $q->whereHas('product', fn ($p) => $p->search($term)))
            ->when($request->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id');

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'stock-movements', 'Stock movements', [
                'Date' => 'text', 'SKU' => 'text', 'Product' => 'text', 'Warehouse' => 'text', 'Type' => 'text',
                'Reference' => 'text', 'Qty' => 'qty', 'Balance' => 'qty', 'By' => 'text',
            ], $movements->limit(20000)->get()->map(fn ($m) => [
                $m->created_at->format('Y-m-d H:i'), $m->product->sku, $m->product->name, $m->warehouse->name,
                str_replace('_', ' ', $m->type), $m->reference?->number ?? $m->note, $m->quantity, $m->balance_after, $m->user?->name,
            ]), $this->filterSummary($request));
        }

        $movements = $movements->paginate(40)->withQueryString();

        return view('stock.movements', [
            'movements' => $movements,
            'warehouses' => Warehouse::orderBy('name')->get(),
        ]);
    }

    public function adjustForm()
    {
        return view('stock.adjust', ['warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get()]);
    }

    /** Set counted quantities (stock take) or add/remove stock, with a reason. */
    public function adjust(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'mode' => ['required', 'in:set,add'],
            'reason' => ['required', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $item) {
                $change = (float) $item['quantity'];

                if ($data['mode'] === 'set') {
                    $current = (float) StockLevel::where('product_id', $item['product_id'])
                        ->where('warehouse_id', $data['warehouse_id'])
                        ->value('quantity');
                    $change = round((float) $item['quantity'] - $current, 2);
                }

                if ($change != 0) {
                    $this->stock->move($item['product_id'], $data['warehouse_id'], $change, 'adjustment', null, $data['reason'], true);
                }
            }
        });

        AuditLog::record('stock.adjust', null, sprintf('Stock %s in %s: %d line(s). Reason: %s',
            $data['mode'] === 'set' ? 'count' : 'adjustment', Warehouse::find($data['warehouse_id'])->name, count($data['items']), $data['reason']));

        return redirect()->route('stock.movements', ['type' => 'adjustment'])->with('success', 'Stock adjusted.');
    }

    public function transferForm()
    {
        return view('stock.transfer', ['warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get()]);
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'note' => ['nullable', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        DB::transaction(function () use ($data) {
            $from = Warehouse::find($data['from_warehouse_id']);
            $to = Warehouse::find($data['to_warehouse_id']);
            $note = trim("Transfer {$from->name} → {$to->name}. ".($data['note'] ?? ''));

            foreach ($data['items'] as $item) {
                $this->stock->move($item['product_id'], $from->id, -(float) $item['quantity'], 'transfer_out', null, $note);
                $this->stock->move($item['product_id'], $to->id, (float) $item['quantity'], 'transfer_in', null, $note);
            }
        });

        return redirect()->route('stock.movements')->with('success', 'Stock transferred.');
    }
}
