<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Support\DocumentNumber;
use App\Support\Totals;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesService
{
    public function __construct(private StockService $stock) {}

    public function saveOrder(array $data, ?SalesOrder $order = null): SalesOrder
    {
        return DB::transaction(function () use ($data, $order) {
            $totals = Totals::calculate($this->lines($data['items']), (float) ($data['discount'] ?? 0), (float) ($data['tax_percent'] ?? 0));

            $header = [
                'customer_id' => $data['customer_id'],
                'warehouse_id' => $data['warehouse_id'],
                'order_date' => $data['order_date'],
                'notes' => $data['notes'] ?? null,
            ] + collect($totals)->except('lines')->all();

            if ($order) {
                $order->update($header);
                $order->items()->delete();
            } else {
                $order = SalesOrder::create($header + [
                    'number' => DocumentNumber::next('sales_orders', 'SO'),
                    'status' => 'pending',
                    'source' => $data['source'] ?? 'office',
                    'rep_visit_id' => $data['rep_visit_id'] ?? null,
                    'user_id' => auth()->id(),
                ]);
            }

            $order->items()->createMany($totals['lines']);

            return $order;
        });
    }

    /**
     * Create an invoice and take the goods out of stock.
     * $extra: additional invoice columns.
     */
    public function createInvoice(array $data, ?SalesOrder $order = null, bool $overrideCredit = false, array $extra = []): Invoice
    {
        return DB::transaction(function () use ($data, $order, $overrideCredit, $extra) {
            $customer = Customer::lockForUpdate()->findOrFail($data['customer_id']);
            $totals = Totals::calculate($this->lines($data['items']), (float) ($data['discount'] ?? 0), (float) ($data['tax_percent'] ?? 0));

            $this->checkCredit($customer, $totals['total'], $overrideCredit);

            $invoiceDate = Carbon::parse($data['invoice_date']);
            $invoice = Invoice::create([
                'number' => DocumentNumber::next('invoices', 'INV'),
                'customer_id' => $customer->id,
                'sales_order_id' => $order?->id,
                'warehouse_id' => $data['warehouse_id'],
                'invoice_date' => $invoiceDate,
                'due_date' => $invoiceDate->copy()->addDays($customer->payment_terms_days),
                'status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
                'user_id' => auth()->id(),
            ] + $extra + collect($totals)->except('lines')->all());

            $costs = Product::whereIn('id', collect($totals['lines'])->pluck('product_id'))->pluck('cost_price', 'id');

            foreach ($totals['lines'] as $line) {
                $invoice->items()->create($line + ['unit_cost' => $costs[$line['product_id']] ?? 0]);
                $this->stock->move($line['product_id'], $invoice->warehouse_id, -$line['quantity'], 'sale', $invoice, "Invoice {$invoice->number}");
            }

            $order?->update(['status' => 'invoiced']);

            if ($invoice->total <= 0) {
                $invoice->refreshPaymentStatus();
            }

            return $invoice;
        });
    }

    public function invoiceFromOrder(SalesOrder $order, string $invoiceDate, bool $overrideCredit = false): Invoice
    {
        if ($order->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Only pending orders can be invoiced.']);
        }

        $taxPercent = ((float) $order->subtotal - (float) $order->discount) > 0
            ? round((float) $order->tax / ((float) $order->subtotal - (float) $order->discount) * 100, 4)
            : 0;

        return $this->createInvoice([
            'customer_id' => $order->customer_id,
            'warehouse_id' => $order->warehouse_id,
            'invoice_date' => $invoiceDate,
            'discount' => $order->discount,
            'tax_percent' => $taxPercent,
            'notes' => $order->notes,
            'items' => $order->items->map->only(['product_id', 'quantity', 'unit_price', 'discount_percent', 'tax_percent'])->all(),
        ], $order, $overrideCredit);
    }

    /** Cancel an unpaid invoice and put the stock back. */
    public function cancelInvoice(Invoice $invoice): void
    {
        if ($invoice->status === 'cancelled') {
            return;
        }
        if ($invoice->allocations()->exists() || $invoice->creditNotes()->exists()) {
            throw ValidationException::withMessages(['status' => 'This invoice has payments or credit notes. Use a credit note instead of cancelling.']);
        }

        DB::transaction(function () use ($invoice) {
            foreach ($invoice->items as $item) {
                $this->stock->move($item->product_id, $invoice->warehouse_id, (float) $item->quantity, 'cancel', $invoice, "Cancelled invoice {$invoice->number}", true);
            }
            $invoice->update(['status' => 'cancelled']);
            AuditLog::record('invoice.cancel', $invoice, "Cancelled invoice {$invoice->number} ({$invoice->customer->name}, ".money($invoice->total).')');
            $invoice->salesOrder?->update(['status' => 'pending']);
        });
    }

    /**
     * Return goods / credit an invoice. $quantities maps invoice_item_id => qty returned.
     */
    public function createCreditNote(Invoice $invoice, array $quantities, array $data): CreditNote
    {
        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages(['status' => 'Cannot credit a cancelled invoice.']);
        }

        return DB::transaction(function () use ($invoice, $quantities, $data) {
            $restock = (bool) ($data['restock'] ?? false);
            $note = CreditNote::create([
                'number' => DocumentNumber::next('credit_notes', 'CN'),
                'customer_id' => $invoice->customer_id,
                'invoice_id' => $invoice->id,
                'warehouse_id' => $invoice->warehouse_id,
                'credit_date' => $data['credit_date'],
                'reason' => $data['reason'] ?? null,
                'restock' => $restock,
                'user_id' => auth()->id(),
            ]);

            $lineSum = 0;
            foreach ($invoice->items()->lockForUpdate()->get() as $item) {
                $qty = round((float) ($quantities[$item->id] ?? 0), 2);
                if ($qty <= 0) {
                    continue;
                }
                if ($qty > $item->returnableQuantity()) {
                    throw ValidationException::withMessages([
                        "quantities.{$item->id}" => "Only {$item->returnableQuantity()} of {$item->product->sku} can be returned.",
                    ]);
                }

                $lineTotal = round($qty * $item->netUnitPrice(), 2);
                $note->items()->create([
                    'invoice_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $qty,
                    'unit_price' => $item->netUnitPrice(),
                    'line_total' => $lineTotal,
                ]);
                $item->increment('returned_quantity', $qty);
                $lineSum += $lineTotal;

                if ($restock) {
                    $this->stock->move($item->product_id, $invoice->warehouse_id, $qty, 'return', $note, "Credit note {$note->number}");
                }
            }

            if ($lineSum <= 0) {
                throw ValidationException::withMessages(['quantities' => 'Enter a return quantity for at least one item.']);
            }

            // Apply the invoice's bill discount (proportionally) and each line's own tax rate.
            // Invoices from before per-line tax have one tax % for the whole bill: use its overall factor.
            $ratio = (float) $invoice->subtotal > 0 ? ((float) $invoice->subtotal - (float) $invoice->discount) / (float) $invoice->subtotal : 1;
            $hasLineTax = $invoice->items()->where('tax_percent', '>', 0)->exists() || (float) $invoice->tax == 0;
            $total = $hasLineTax
                ? round($note->items()->with('invoiceItem')->get()->sum(fn ($ci) => (float) $ci->line_total * $ratio * (1 + (float) $ci->invoiceItem->tax_percent / 100)), 2)
                : round($lineSum * ((float) $invoice->subtotal > 0 ? (float) $invoice->total / (float) $invoice->subtotal : 1), 2);
            $note->update(['total' => $total]);

            $invoice->increment('credited_amount', $total);
            $this->releaseOverpayment($invoice->fresh());
            $invoice->refresh()->refreshPaymentStatus();

            return $note;
        });
    }

    /**
     * If credits push an invoice below zero, free the excess payment allocations so the
     * money becomes customer credit that can be applied to other invoices.
     */
    private function releaseOverpayment(Invoice $invoice): void
    {
        $excess = -$invoice->balance();

        foreach ($invoice->allocations()->latest('id')->get() as $allocation) {
            if ($excess <= 0) {
                break;
            }
            $reduce = min($excess, (float) $allocation->amount);
            $remaining = round((float) $allocation->amount - $reduce, 2);
            $remaining > 0 ? $allocation->update(['amount' => $remaining]) : $allocation->delete();
            $excess = round($excess - $reduce, 2);
        }
    }

    private function checkCredit(Customer $customer, float $newAmount, bool $override): void
    {
        if ($customer->on_hold && ! $override) {
            throw ValidationException::withMessages(['customer_id' => "{$customer->name} is on hold. A manager must override to invoice."]);
        }

        if (! config('distributor.enforce_credit_limit') || (float) $customer->credit_limit <= 0 || $override) {
            return;
        }

        $after = $customer->outstandingBalance() + $newAmount;
        if ($after > (float) $customer->credit_limit) {
            throw ValidationException::withMessages([
                'customer_id' => sprintf(
                    'Credit limit exceeded for %s. Limit %s, balance after this invoice %s. A manager must override.',
                    $customer->name,
                    number_format((float) $customer->credit_limit, 2),
                    number_format($after, 2),
                ),
            ]);
        }
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
