<?php

namespace App\Services;

use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * The only place stock quantities change. Call inside a DB transaction.
 */
class StockService
{
    public function move(
        int $productId,
        int $warehouseId,
        float $quantity,
        string $type,
        ?Model $reference = null,
        ?string $note = null,
        bool $allowNegative = false,
    ): StockMovement {
        $level = StockLevel::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first()
            ?? StockLevel::create(['product_id' => $productId, 'warehouse_id' => $warehouseId, 'quantity' => 0]);

        $newBalance = round((float) $level->quantity + $quantity, 2);

        if ($newBalance < 0 && ! $allowNegative && ! config('distributor.allow_negative_stock')) {
            $product = $level->product;
            throw ValidationException::withMessages([
                'stock' => "Not enough stock for {$product->sku} - {$product->name}. Available: "
                    .number_format((float) $level->quantity, 2).', required: '.number_format(abs($quantity), 2).'.',
            ]);
        }

        $level->update(['quantity' => $newBalance]);

        return StockMovement::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $newBalance,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'note' => $note,
            'user_id' => auth()->id(),
        ]);
    }
}
