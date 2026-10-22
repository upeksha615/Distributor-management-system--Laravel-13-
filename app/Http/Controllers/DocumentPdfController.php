<?php

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Support\Exporter;

/** "Download PDF" for individual documents, rendered with resources/views/pdf/document.blade.php. */
class DocumentPdfController extends Controller
{
    private const SALE_COLUMNS = ['#' => 'number', 'SKU' => 'text', 'Description' => 'text', 'Qty' => 'qty', 'Price' => 'money', 'Disc %' => 'qty', 'Total' => 'money'];

    public function invoice(Invoice $invoice)
    {
        $this->authorizeDealer($invoice->customer);
        $invoice->load(['customer', 'items.product', 'salesOrder']);

        $totals = $this->saleTotals($invoice);
        if ($invoice->status !== 'cancelled') {
            $totals['Invoice total'] = $totals['Total'];
            unset($totals['Total']);
            if ($invoice->credited_amount > 0) {
                $totals['Credit notes'] = -$invoice->credited_amount;
            }
            $totals['Paid'] = -$invoice->paid_amount;
            $totals['Balance due'] = $invoice->balance();
        }

        return $this->render('Invoice '.$invoice->number, [
            'docTitle' => $invoice->status === 'cancelled' ? 'INVOICE (CANCELLED)' : ($invoice->tax > 0 ? 'TAX INVOICE' : 'INVOICE'),
            'docNumber' => $invoice->number,
            'party' => $this->customerParty('Bill to', $invoice->customer),
            'meta' => array_filter([
                'Invoice date' => $invoice->invoice_date->format('d/m/Y'),
                'Due date' => $invoice->due_date->format('d/m/Y'),
                'Customer code' => $invoice->customer->code,
                'Order' => $invoice->salesOrder?->number,
                'Terms' => $invoice->customer->payment_terms_days.' days',
            ]),
            'columns' => self::SALE_COLUMNS,
            'rows' => $this->saleRows($invoice->items),
            'totals' => $totals,
            'notes' => $invoice->notes,
            'signatures' => ['Authorised signature', 'Received in good condition'],
        ]);
    }

    public function salesOrder(SalesOrder $salesOrder)
    {
        $this->authorizeDealer($salesOrder->customer);
        $salesOrder->load(['customer', 'items.product', 'warehouse']);

        return $this->render('Sales order '.$salesOrder->number, [
            'docTitle' => 'SALES ORDER',
            'docNumber' => $salesOrder->number,
            'party' => $this->customerParty('Customer', $salesOrder->customer),
            'meta' => ['Order date' => $salesOrder->order_date->format('d/m/Y'), 'Warehouse' => $salesOrder->warehouse->name, 'Status' => ucfirst($salesOrder->status)],
            'columns' => self::SALE_COLUMNS,
            'rows' => $this->saleRows($salesOrder->items),
            'totals' => $this->saleTotals($salesOrder),
            'notes' => $salesOrder->notes,
        ]);
    }

    public function purchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $po = $purchaseOrder->load(['supplier', 'warehouse', 'items.product']);

        return $this->render('Purchase order '.$po->number, [
            'docTitle' => 'PURCHASE ORDER',
            'docNumber' => $po->number,
            'party' => ['label' => 'Supplier', 'lines' => [$po->supplier->name, $po->supplier->address, $po->supplier->country, trim($po->supplier->phone.' '.$po->supplier->email)]],
            'meta' => array_filter([
                'Order date' => $po->order_date->format('d/m/Y'),
                'Expected' => $po->expected_date?->format('d/m/Y'),
                'Deliver to' => $po->warehouse->name.($po->warehouse->location ? ', '.$po->warehouse->location : ''),
            ]),
            'columns' => ['#' => 'number', 'SKU' => 'text', 'Description' => 'text', 'Qty' => 'qty', 'Unit cost' => 'money', 'Total' => 'money'],
            'rows' => $po->items->values()->map(fn ($i, $n) => [$n + 1, $i->product->sku, $i->product->name, $i->quantity, $i->unit_cost, $i->line_total])->all(),
            'totals' => ['Total' => $po->total],
            'notes' => $po->notes,
            'signatures' => ['Prepared by', 'Approved by'],
        ]);
    }

    public function goodsReceipt(GoodsReceipt $goodsReceipt)
    {
        $grn = $goodsReceipt->load(['purchaseOrder.supplier', 'warehouse', 'items.product', 'user']);

        return $this->render('GRN '.$grn->number, [
            'docTitle' => 'GOODS RECEIVED NOTE',
            'docNumber' => $grn->number,
            'party' => ['label' => 'Supplier', 'lines' => [$grn->purchaseOrder->supplier->name, $grn->purchaseOrder->supplier->country]],
            'meta' => array_filter([
                'Received date' => $grn->received_date->format('d/m/Y'),
                'Purchase order' => $grn->purchaseOrder->number,
                'Supplier invoice' => $grn->supplier_invoice_no,
                'Warehouse' => $grn->warehouse->name,
                'Received by' => $grn->user?->name,
            ]),
            'columns' => ['#' => 'number', 'SKU' => 'text', 'Description' => 'text', 'Qty' => 'qty', 'Unit cost' => 'money', 'Value' => 'money'],
            'rows' => $grn->items->values()->map(fn ($i, $n) => [$n + 1, $i->product->sku, $i->product->name, $i->quantity, $i->unit_cost, $i->quantity * $i->unit_cost])->all(),
            'totals' => ['Total' => $grn->total],
            'notes' => $grn->notes,
            'signatures' => ['Received by', 'Checked by'],
        ]);
    }

    public function creditNote(CreditNote $creditNote)
    {
        $this->authorizeDealer($creditNote->customer);
        $note = $creditNote->load(['customer', 'invoice', 'warehouse', 'items.product']);

        return $this->render('Credit note '.$note->number, [
            'docTitle' => 'CREDIT NOTE',
            'docNumber' => $note->number,
            'party' => $this->customerParty('Customer', $note->customer),
            'meta' => [
                'Date' => $note->credit_date->format('d/m/Y'),
                'Against invoice' => $note->invoice->number,
                'Reason' => $note->reason,
                'Goods restocked' => $note->restock ? 'Yes' : 'No',
            ],
            'columns' => ['SKU' => 'text', 'Description' => 'text', 'Qty' => 'qty', 'Net price' => 'money', 'Total' => 'money'],
            'rows' => $note->items->map(fn ($i) => [$i->product->sku, $i->product->name, $i->quantity, $i->unit_price, $i->line_total])->all(),
            'totals' => ['Lines' => $note->items->sum('line_total'), 'Credit total (incl. discount/tax share)' => $note->total],
        ]);
    }

    public function payment(Payment $payment)
    {
        abort_unless($payment->status === 'verified', 403, 'Receipts are issued only for verified payments.');
        $this->authorizeDealer($payment->customer);
        $payment->load(['customer', 'allocations.invoice', 'user']);

        $rows = $payment->allocations->map(fn ($a) => [$a->invoice->number, $a->invoice->invoice_date->format('d/m/Y'), $a->amount])->all();
        if (($left = $payment->unallocatedAmount()) > 0) {
            $rows[] = ['On account (unallocated)', '', $left];
        }

        return $this->render('Receipt '.$payment->number, [
            'docTitle' => $payment->isSettled() ? 'PAYMENT RECEIPT' : 'CHEQUE ACKNOWLEDGEMENT (SUBJECT TO CLEARANCE)',
            'docNumber' => $payment->number,
            'party' => $this->customerParty('Received from', $payment->customer),
            'meta' => array_filter([
                'Date' => $payment->payment_date->format('d/m/Y'),
                'Method' => $payment->methodLabel(),
                'Cheque no' => $payment->cheque_number,
                'Bank' => $payment->cheque_bank,
                'Cheque date' => $payment->cheque_date?->format('d/m/Y'),
                'Reference' => $payment->reference,
                'Received by' => $payment->user?->name,
            ]),
            'columns' => ['Applied to invoice' => 'text', 'Invoice date' => 'text', 'Amount' => 'money'],
            'rows' => $rows,
            'totals' => ['Amount received' => $payment->amount],
            'signatures' => ['Received by', 'Customer'],
        ]);
    }

    private function render(string $name, array $data)
    {
        return Exporter::pdfView('pdf.document', $data, str($name)->slug());
    }

    private function customerParty(string $label, Customer $c): array
    {
        return ['label' => $label, 'lines' => [
            $c->name.' ('.$c->code.')',
            trim($c->address.' '.$c->city),
            $c->phone,
            $c->tax_number ? 'Tax no: '.$c->tax_number : null,
        ]];
    }

    private function saleRows($items): array
    {
        return $items->values()->map(fn ($i, $n) => [
            $n + 1, $i->product->sku, $i->product->name, $i->quantity, $i->unit_price,
            $i->discount_percent > 0 ? $i->discount_percent : null, $i->line_total,
        ])->all();
    }

    private function saleTotals($doc): array
    {
        return array_filter([
            'Subtotal' => $doc->subtotal,
            'Discount' => $doc->discount > 0 ? -$doc->discount : null,
            'Tax' => $doc->tax > 0 ? $doc->tax : null,
        ], fn ($v) => $v !== null) + ['Total' => $doc->total];
    }
}
