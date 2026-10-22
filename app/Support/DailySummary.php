<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Shipment;
use App\Models\SupplierBill;
use Illuminate\Support\Facades\DB;

/** Figures for the morning summary email. Uses the same rules as the dashboard (only settled money counts). */
class DailySummary
{
    public static function build(): array
    {
        $balance = DB::raw('total - paid_amount - credited_amount');
        $overdue = Invoice::open()->where('due_date', '<', today());
        $chequesDue = Payment::where('method', 'cheque')->where('cheque_status', 'received')->whereNotIn('status', ['rejected', 'reversed'])
            ->where('cheque_date', '<=', today());
        $lowStock = Product::where('is_active', true)->where('reorder_level', '>', 0)->withTotalStock()->get()
            ->filter(fn ($p) => (float) $p->total_stock <= $p->reorder_level)->count();

        $rows = [
            ['label' => 'Payments waiting for verification', 'count' => Payment::awaitingVerification()->count(), 'amount' => (float) Payment::awaitingVerification()->sum('amount')],
            ['label' => 'Payment slips in the inbox', 'count' => PaymentSubmission::whereIn('status', ['new', 'matched', 'info_requested'])->count(), 'amount' => null],
            ['label' => 'Cheques to deposit (dated today or earlier)', 'count' => (clone $chequesDue)->count(), 'amount' => (float) (clone $chequesDue)->sum('amount')],
            ['label' => 'Cheques deposited, awaiting clearance', 'count' => Payment::where('cheque_status', 'deposited')->count(), 'amount' => (float) Payment::where('cheque_status', 'deposited')->sum('amount')],
            ['label' => 'Cheques returned yesterday', 'count' => Payment::where('cheque_status', 'returned')->whereDate('returned_date', today()->subDay())->count(), 'amount' => (float) Payment::where('cheque_status', 'returned')->whereDate('returned_date', today()->subDay())->sum('amount')],
            ['label' => 'Overdue dealer invoices', 'count' => (clone $overdue)->count(), 'amount' => (float) (clone $overdue)->sum($balance)],
            ['label' => 'Supplier bills due within 7 days', 'count' => SupplierBill::open()->where('due_date', '<=', today()->addDays(7))->count(), 'amount' => (float) SupplierBill::open()->where('due_date', '<=', today()->addDays(7))->sum(DB::raw('amount - paid_amount'))],
            ['label' => 'Sales orders pending', 'count' => SalesOrder::where('status', 'pending')->count(), 'amount' => null],
            ['label' => 'Products at or below reorder level', 'count' => $lowStock, 'amount' => null],
            ['label' => 'Shipments past their expected date', 'count' => Shipment::whereIn('status', ['pending', 'partial'])->where('expected_date', '<', today())->count(), 'amount' => null],
        ];

        return [
            'rows' => $rows,
            'sales_yesterday' => (float) Invoice::where('status', '!=', 'cancelled')->whereDate('invoice_date', today()->subDay())->sum('total'),
            'collected_yesterday' => (float) Payment::settled()->whereDate('payment_date', today()->subDay())->sum('amount'),
        ];
    }
}
