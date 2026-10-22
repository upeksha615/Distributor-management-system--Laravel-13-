<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Support\DocumentNumber;
use App\Support\Totals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public const VALID_DAYS = 14;

    public function __construct(private SalesService $sales) {}

    public function save(array $data, ?Quotation $quote = null): Quotation
    {
        return DB::transaction(function () use ($data, $quote) {
            $totals = Totals::calculate($this->lines($data['items']), (float) ($data['discount'] ?? 0), (float) ($data['tax_percent'] ?? 0));

            $header = [
                'customer_id' => $data['customer_id'],
                'warehouse_id' => $data['warehouse_id'],
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?? now()->parse($data['quote_date'])->addDays(self::VALID_DAYS),
                'notes' => $data['notes'] ?? null,
            ] + collect($totals)->except('lines')->all();

            if ($quote) {
                $quote->update($header);
                $quote->items()->delete();
            } else {
                $quote = Quotation::create($header + [
                    'number' => DocumentNumber::next('quotations', 'QT'),
                    'status' => 'open',
                    'user_id' => auth()->id(),
                ]);
            }

            $quote->items()->createMany($totals['lines']);

            return $quote;
        });
    }

    public function convertToOrder(Quotation $quote): SalesOrder
    {
        $this->ensureConvertible($quote);

        return DB::transaction(function () use ($quote) {
            $order = $this->sales->saveOrder($this->documentData($quote) + ['order_date' => today()->toDateString()]);
            $quote->update(['status' => 'converted', 'sales_order_id' => $order->id]);

            return $order;
        });
    }

    public function convertToInvoice(Quotation $quote, bool $overrideCredit = false): Invoice
    {
        $this->ensureConvertible($quote);

        return DB::transaction(function () use ($quote, $overrideCredit) {
            $invoice = $this->sales->createInvoice($this->documentData($quote) + ['invoice_date' => today()->toDateString()], null, $overrideCredit);
            $quote->update(['status' => 'converted', 'invoice_id' => $invoice->id]);

            return $invoice;
        });
    }

    /** Tax percentage implied by the stored header totals. */
    public static function taxPercent(Quotation $quote, int $precision = 4): float
    {
        $net = (float) $quote->subtotal - (float) $quote->discount;

        return $net > 0 ? round((float) $quote->tax / $net * 100, $precision) : 0;
    }

    private function ensureConvertible(Quotation $quote): void
    {
        if (! in_array($quote->status, ['open', 'accepted'], true)) {
            throw ValidationException::withMessages(['status' => 'Only open or accepted quotations can be converted.']);
        }
    }

    private function documentData(Quotation $quote): array
    {
        $quote->loadMissing('items');

        return [
            'customer_id' => $quote->customer_id,
            'warehouse_id' => $quote->warehouse_id,
            'discount' => $quote->discount,
            'tax_percent' => self::taxPercent($quote),
            'notes' => trim("Quotation {$quote->number}. ".($quote->notes ?? '')),
            'items' => $quote->items->map->only(['product_id', 'quantity', 'unit_price', 'discount_percent', 'tax_percent'])->all(),
        ];
    }

    private function lines(array $items): array
    {
        return collect($items)->map(fn ($l) => [
            'product_id' => (int) $l['product_id'],
            'quantity' => (float) $l['quantity'],
            'unit_price' => (float) $l['unit_price'],
            'discount_percent' => (float) ($l['discount_percent'] ?? 0),
            // Per-line tax (from the product); null falls back to the document's tax %.
            'tax_percent' => isset($l['tax_percent']) && $l['tax_percent'] !== '' ? (float) $l['tax_percent'] : null,
        ])->values()->all();
    }
}
