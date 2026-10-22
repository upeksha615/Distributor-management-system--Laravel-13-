<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Shipment;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Upcoming shipments. Uploaded quantities are pending and do NOT change stock;
 * stock changes only when (part of) a shipment is received.
 */
class ShipmentService
{
    public function __construct(private StockService $stock) {}

    /**
     * Validate spreadsheet rows (keyed by spreadsheet row number) of product_code, quantity, optional unit_cost.
     * Returns [lines, errors]. Lines for the same product code are added together.
     */
    public function parseRows(array $rows): array
    {
        $errors = [];
        $lines = [];
        if (! $rows) {
            return [[], [0 => 'The file has no data rows.']];
        }
        $first = reset($rows);
        $codeKey = collect(['product_code', 'code', 'sku', 'item_code'])->first(fn ($k) => array_key_exists($k, $first));
        $qtyKey = collect(['quantity', 'qty'])->first(fn ($k) => array_key_exists($k, $first));
        if (! $codeKey || ! $qtyKey) {
            return [[], [1 => 'The file needs the columns "product_code" and "quantity". Download the template.']];
        }

        $codes = collect($rows)->pluck($codeKey)->filter()->map(fn ($c) => trim((string) $c))->unique();
        $products = Product::whereIn('sku', $codes)->get()->keyBy(fn ($p) => mb_strtolower($p->sku));

        foreach ($rows as $line => $row) {
            $code = trim((string) ($row[$codeKey] ?? ''));
            $qty = $row[$qtyKey] ?? '';
            $cost = $row['unit_cost'] ?? $row['cost'] ?? '';
            if ($code === '' && ($qty === '' || $qty === null)) {
                continue;
            }
            $product = $products[mb_strtolower($code)] ?? null;
            $problems = [];
            if (! $product) {
                $problems[] = "Unknown product code \"{$code}\".";
            }
            if (! is_numeric($qty) || (float) $qty <= 0) {
                $problems[] = 'Quantity must be a number above 0.';
            }
            if ($cost !== '' && $cost !== null && (! is_numeric($cost) || (float) $cost < 0)) {
                $problems[] = 'Unit cost must be a number.';
            }
            if ($problems) {
                $errors[$line] = implode(' ', $problems);

                continue;
            }
            $lines[$product->id] ??= ['product_id' => $product->id, 'quantity' => 0, 'unit_cost' => null];
            $lines[$product->id]['quantity'] += (float) $qty;
            if ($cost !== '' && $cost !== null) {
                $lines[$product->id]['unit_cost'] = (float) $cost;
            }
        }

        return [array_values($lines), $errors];
    }

    public function create(array $data, array $lines): Shipment
    {
        if (! $lines) {
            throw ValidationException::withMessages(['file' => 'No valid lines to import.']);
        }

        return DB::transaction(function () use ($data, $lines) {
            $shipment = Shipment::create([
                'number' => DocumentNumber::next('shipments', 'SHP'),
                'reference' => $data['reference'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'expected_date' => $data['expected_date'] ?? null,
                'status' => 'pending',
                'source_file' => $data['source_file'] ?? null,
                'notes' => $data['notes'] ?? null,
                'user_id' => auth()->id(),
            ]);
            $shipment->items()->createMany($lines);
            AuditLog::record('shipment.upload', $shipment, "Upcoming shipment {$shipment->number} uploaded: ".count($lines).' product(s), '
                .qty(collect($lines)->sum('quantity')).' units'.($shipment->reference ? ", ref {$shipment->reference}" : '').'.');

            return $shipment;
        });
    }

    /** Receive goods: quantities map shipment_item_id => qty. Adds them to stock. */
    public function receive(Shipment $shipment, array $quantities, ?string $note = null): void
    {
        if (! $shipment->isOpen()) {
            throw ValidationException::withMessages(['status' => "{$shipment->number} is ".strtolower(Shipment::STATUSES[$shipment->status]).'.']);
        }

        DB::transaction(function () use ($shipment, $quantities, $note) {
            $received = 0;
            foreach ($shipment->items()->with('product')->lockForUpdate()->get() as $item) {
                $qty = round((float) ($quantities[$item->id] ?? 0), 2);
                if ($qty <= 0) {
                    continue;
                }
                if ($qty > $item->outstanding()) {
                    throw ValidationException::withMessages(["quantities.{$item->id}" => "Only {$item->outstanding()} of {$item->product->sku} are still expected."]);
                }
                $item->increment('received_quantity', $qty);
                $this->stock->move($item->product_id, $shipment->warehouse_id, $qty, 'shipment', $shipment, "Shipment {$shipment->number}".($note ? " - {$note}" : ''));
                if ($item->unit_cost !== null) {
                    Product::whereKey($item->product_id)->update(['cost_price' => $item->unit_cost]);
                }
                $received += $qty;
            }
            if ($received <= 0) {
                throw ValidationException::withMessages(['quantities' => 'Enter a received quantity for at least one product.']);
            }

            $done = $shipment->items()->whereColumn('received_quantity', '<', 'quantity')->doesntExist();
            $shipment->update(['status' => $done ? 'received' : 'partial', 'received_at' => $done ? now() : $shipment->received_at]);
            AuditLog::record('shipment.receive', $shipment, "Received ".qty($received)." units from {$shipment->number} into stock".($done ? ' (complete).' : ' (partial).'));
        });
    }

    /** Cancel what is still expected (e.g. shipment lost or replaced). Received goods stay in stock. */
    public function cancel(Shipment $shipment, string $reason): void
    {
        if (! $shipment->isOpen()) {
            throw ValidationException::withMessages(['status' => 'Only pending shipments can be cancelled.']);
        }
        $shipment->update(['status' => $shipment->items()->where('received_quantity', '>', 0)->exists() ? 'received' : 'cancelled',
            'notes' => trim(($shipment->notes ? $shipment->notes."\n" : '').'Cancelled: '.$reason)]);
        AuditLog::record('shipment.cancel', $shipment, "{$shipment->number} cancelled: {$reason}");
    }
}
