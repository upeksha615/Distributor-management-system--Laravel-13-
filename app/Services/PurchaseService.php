<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(private StockService $stock) {}

    /** Create or update a draft purchase order. */
    public function save(array $data, ?PurchaseOrder $po = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $po) {
            $lines = collect($data['items'])->map(fn ($l) => [
                'product_id' => (int) $l['product_id'],
                'quantity' => (float) $l['quantity'],
                'unit_cost' => (float) $l['unit_cost'],
                'line_total' => round((float) $l['quantity'] * (float) $l['unit_cost'], 2),
            ]);

            $header = [
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'total' => $lines->sum('line_total'),
            ];

            if ($po) {
                $po->update($header);
                $po->items()->delete();
            } else {
                $po = PurchaseOrder::create($header + [
                    'number' => DocumentNumber::next('purchase_orders', 'PO'),
                    'status' => 'draft',
                    'user_id' => auth()->id(),
                ]);
            }

            $po->items()->createMany($lines->all());

            return $po;
        });
    }

    /**
     * Receive goods against a purchase order. $quantities maps purchase_order_item_id => qty received.
     */
    public function receive(PurchaseOrder $po, array $quantities, array $data): GoodsReceipt
    {
        if (! $po->canReceive()) {
            throw ValidationException::withMessages(['status' => 'Only ordered or partially received POs can be received.']);
        }

        return DB::transaction(function () use ($po, $quantities, $data) {
            $grn = GoodsReceipt::create([
                'number' => DocumentNumber::next('goods_receipts', 'GRN'),
                'purchase_order_id' => $po->id,
                'warehouse_id' => $po->warehouse_id,
                'received_date' => $data['received_date'],
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'user_id' => auth()->id(),
            ]);

            $total = 0;

            foreach ($po->items()->lockForUpdate()->get() as $item) {
                $qty = round((float) ($quantities[$item->id] ?? 0), 2);
                if ($qty <= 0) {
                    continue;
                }
                if ($qty > $item->outstanding()) {
                    throw ValidationException::withMessages([
                        "quantities.{$item->id}" => "Cannot receive more than the outstanding {$item->outstanding()} for {$item->product->sku}.",
                    ]);
                }

                $grn->items()->create([
                    'purchase_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $qty,
                    'unit_cost' => $item->unit_cost,
                ]);
                $item->increment('received_quantity', $qty);
                $total += $qty * (float) $item->unit_cost;

                $this->stock->move($item->product_id, $po->warehouse_id, $qty, 'purchase', $grn, "GRN {$grn->number}");
                Product::whereKey($item->product_id)->update(['cost_price' => $item->unit_cost]);
            }

            if ($total <= 0) {
                throw ValidationException::withMessages(['quantities' => 'Enter a quantity for at least one item.']);
            }

            $grn->update(['total' => round($total, 2)]);

            $fullyReceived = $po->items()->whereColumn('received_quantity', '<', 'quantity')->doesntExist();
            $po->update(['status' => $fullyReceived ? 'received' : 'partial']);

            app(PayablesService::class)->billFromGoodsReceipt($grn);

            return $grn;
        });
    }
}
