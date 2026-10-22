<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Support\DocumentNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Accounts payable: supplier bills and the payments we make against them. */
class PayablesService
{
    /** Create a supplier bill (from a GRN or a manual bill for freight, duty, clearing, rent...). */
    public function createBill(Supplier $supplier, array $data): SupplierBill
    {
        return DB::transaction(function () use ($supplier, $data) {
            $billDate = Carbon::parse($data['bill_date']);

            $bill = SupplierBill::create([
                'number' => DocumentNumber::next('supplier_bills', 'BILL'),
                'supplier_id' => $supplier->id,
                'goods_receipt_id' => $data['goods_receipt_id'] ?? null,
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'bill_date' => $billDate->toDateString(),
                'due_date' => ! empty($data['due_date'])
                    ? $data['due_date']
                    : $billDate->copy()->addDays((int) ($supplier->payment_terms_days ?? 30))->toDateString(),
                'description' => $data['description'] ?? null,
                'amount' => round((float) $data['amount'], 2),
                'paid_amount' => 0,
                'status' => 'unpaid',
                'user_id' => auth()->id(),
            ]);

            AuditLog::record('supplier-bill.create', $bill, "Created bill {$bill->number} for {$supplier->name}: ".number_format((float) $bill->amount, 2));

            return $bill;
        });
    }

    /**
     * Bill the supplier for a goods received note. Idempotent: returns the existing bill if one
     * was already raised for this GRN (also used to backfill GRNs posted before payables existed).
     */
    public function billFromGoodsReceipt(GoodsReceipt $grn): ?SupplierBill
    {
        if ($existing = SupplierBill::where('goods_receipt_id', $grn->id)->first()) {
            return $existing;
        }

        $grn->loadMissing('purchaseOrder.supplier');
        if ((float) $grn->total <= 0) {
            return null;
        }

        return $this->createBill($grn->purchaseOrder->supplier, [
            'goods_receipt_id' => $grn->id,
            'supplier_invoice_no' => $grn->supplier_invoice_no,
            'bill_date' => $grn->received_date,
            'description' => "GRN {$grn->number} / PO {$grn->purchaseOrder->number}",
            'amount' => $grn->total,
        ]);
    }

    /** Cancel a bill. Only allowed while nothing has been paid against it. */
    public function cancelBill(SupplierBill $bill, ?string $reason = null): void
    {
        DB::transaction(function () use ($bill, $reason) {
            $bill = SupplierBill::lockForUpdate()->findOrFail($bill->id);

            if ($bill->status === 'cancelled') {
                return;
            }
            if ($bill->allocations()->exists() || (float) $bill->paid_amount > 0) {
                throw ValidationException::withMessages(['bill' => "Bill {$bill->number} has payments applied and cannot be cancelled."]);
            }

            $bill->update(['status' => 'cancelled']);
            AuditLog::record('supplier-bill.cancel', $bill, "Cancelled bill {$bill->number}".($reason ? ": {$reason}" : ''));
        });
    }

    /**
     * Record a payment to a supplier. $allocations maps supplier_bill_id => amount; when empty and
     * $autoAllocate is true, the money is applied to the oldest open bills first.
     */
    public function recordPayment(Supplier $supplier, array $data, array $allocations = [], bool $autoAllocate = true): SupplierPayment
    {
        return DB::transaction(function () use ($supplier, $data, $allocations, $autoAllocate) {
            $payment = SupplierPayment::create([
                'number' => DocumentNumber::next('supplier_payments', 'PV'),
                'supplier_id' => $supplier->id,
                'payment_date' => $data['payment_date'],
                'method' => $data['method'],
                'amount' => $data['amount'],
                'reference' => $data['reference'] ?? null,
                'cheque_number' => $data['cheque_number'] ?? null,
                'cheque_date' => $data['cheque_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'user_id' => auth()->id(),
            ]);

            $allocations = array_filter($allocations, fn ($a) => (float) $a > 0);

            if ($allocations) {
                $this->allocate($payment, $allocations);
            } elseif ($autoAllocate) {
                $this->autoAllocate($payment);
            }

            AuditLog::record('supplier-payment.create', $payment, "Paid {$supplier->name} ".number_format((float) $payment->amount, 2)." ({$payment->number})");

            return $payment;
        });
    }

    /** Apply part of a supplier payment to specific bills. */
    public function allocate(SupplierPayment $payment, array $allocations): void
    {
        DB::transaction(function () use ($payment, $allocations) {
            $available = $payment->unallocatedAmount();
            $requested = round(array_sum(array_map('floatval', $allocations)), 2);

            if ($requested > $available + 0.001) {
                throw ValidationException::withMessages([
                    'allocations' => 'Allocated total '.number_format($requested, 2).' is more than the available '.number_format($available, 2).'.',
                ]);
            }

            foreach ($allocations as $billId => $amount) {
                $amount = round((float) $amount, 2);
                if ($amount <= 0) {
                    continue;
                }

                $bill = SupplierBill::lockForUpdate()->findOrFail($billId);
                if ($bill->supplier_id !== $payment->supplier_id || ! in_array($bill->status, ['unpaid', 'partial'])) {
                    throw ValidationException::withMessages(['allocations' => "Bill {$bill->number} cannot take this payment."]);
                }
                if ($amount > $bill->balance() + 0.001) {
                    throw ValidationException::withMessages([
                        "allocations.{$billId}" => "Bill {$bill->number} only has ".number_format($bill->balance(), 2).' outstanding.',
                    ]);
                }

                $payment->allocations()->create(['supplier_bill_id' => $bill->id, 'amount' => $amount]);
                $bill->refreshPaymentStatus();
            }
        });
    }

    public function autoAllocate(SupplierPayment $payment): void
    {
        $remaining = $payment->unallocatedAmount();
        $allocations = [];

        $bills = SupplierBill::where('supplier_id', $payment->supplier_id)
            ->open()
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        foreach ($bills as $bill) {
            if ($remaining <= 0) {
                break;
            }
            $amount = min($remaining, $bill->balance());
            if ($amount > 0) {
                $allocations[$bill->id] = $amount;
                $remaining = round($remaining - $amount, 2);
            }
        }

        if ($allocations) {
            $this->allocate($payment, $allocations);
        }
    }
}
