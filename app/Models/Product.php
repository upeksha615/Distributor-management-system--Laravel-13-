<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'min_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function shipmentItems()
    {
        return $this->hasMany(ShipmentItem::class);
    }

    /** Adds incoming_qty: quantity on pending / partly received shipments (not stock yet). */
    public function scopeWithIncoming(Builder $query): Builder
    {
        return $query->withSum(['shipmentItems as incoming_qty' => fn ($q) => $q->whereHas('shipment', fn ($s) => $s->whereIn('status', ['pending', 'partial']))],
            \Illuminate\Support\Facades\DB::raw('quantity - received_quantity'));
    }

    /** Earliest expected date of an open shipment carrying this product. */
    public function nextShipmentDate(): ?\Illuminate\Support\Carbon
    {
        $date = Shipment::whereIn('status', ['pending', 'partial'])->whereHas('items', fn ($q) => $q->where('product_id', $this->id)->whereColumn('received_quantity', '<', 'quantity'))
            ->whereNotNull('expected_date')->min('expected_date');

        return $date ? \Illuminate\Support\Carbon::parse($date) : null;
    }

    /**
     * Stock colour for reps: 'out' (0 or less, red), 'low' (up to the threshold, yellow), 'ok' (above, green).
     */
    public static function stockLevel(float $qty): string
    {
        return match (true) {
            $qty <= 0 => 'out',
            $qty <= (float) config('distributor.stock_low_threshold', 10) => 'low',
            default => 'ok',
        };
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? route('products.image', [$this, 'v' => substr(md5($this->image_path), 0, 8)]) : null;
    }

    public function partNumbers()
    {
        return $this->hasMany(ProductPartNumber::class);
    }

    public function fitments()
    {
        return $this->hasMany(ProductFitment::class);
    }

    /** Adds a total_stock column (all warehouses) to the query. */
    public function scopeWithTotalStock(Builder $query): Builder
    {
        return $query->withSum('stockLevels as total_stock', 'quantity');
    }

    /**
     * Matches SKU, name, OEM part number, barcode, cross-reference numbers and vehicle fitment.
     * Several words must all match, e.g. "brake pad axio".
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        // Part numbers are often typed without dashes/spaces: compare a normalised form too.
        $compact = preg_replace('/[^A-Za-z0-9]/', '', $term);
        $norm = fn (string $col) => "REPLACE(REPLACE(REPLACE(REPLACE({$col}, '-', ''), ' ', ''), '/', ''), '.', '')";

        return $query->where(function ($q) use ($term, $compact, $norm) {
            $q->where('sku', $term)->orWhere('barcode', $term)->orWhere('part_number', $term)
                ->orWhereHas('partNumbers', fn ($p) => $p->where('number', $term));

            if (strlen($compact) >= 3) {
                $q->orWhereRaw($norm('sku').' LIKE ?', ["%{$compact}%"])
                    ->orWhereRaw($norm('part_number').' LIKE ?', ["%{$compact}%"])
                    ->orWhereHas('partNumbers', fn ($p) => $p->whereRaw($norm('number').' LIKE ?', ["%{$compact}%"]));
            }

            $q->orWhere(function ($words) use ($term) {
                foreach (preg_split('/\s+/', $term) as $word) {
                    $words->where(fn ($w) => $w->where('name', 'like', "%{$word}%")
                        ->orWhere('sku', 'like', "%{$word}%")
                        ->orWhere('part_number', 'like', "%{$word}%")
                        ->orWhere('location', $word)
                        ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$word}%"))
                        ->orWhereHas('fitments', fn ($f) => $f->where('make', 'like', "%{$word}%")->orWhere('model', 'like', "%{$word}%")->orWhere('engine', 'like', "%{$word}%")));
                }
            });
        });
    }

    /** Exact match for a barcode scanner / typed code. */
    public static function findByCode(string $code): ?self
    {
        $code = trim($code);

        return static::where('is_active', true)
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', $code)->orWhere('part_number', $code)
                ->orWhereHas('partNumbers', fn ($p) => $p->where('number', $code)))
            ->first();
    }

    public function stockIn(int $warehouseId): float
    {
        return (float) $this->stockLevels->firstWhere('warehouse_id', $warehouseId)?->quantity;
    }

    /** Price for a customer price level; wholesale falls back to retail when not set. */
    public function priceFor(?string $level): float
    {
        return $level === 'wholesale' && (float) $this->wholesale_price > 0
            ? (float) $this->wholesale_price
            : (float) $this->selling_price;
    }
}
