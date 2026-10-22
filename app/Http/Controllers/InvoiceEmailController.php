<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceMail;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Services\MailService;
use Illuminate\Http\Request;

/** Email an invoice PDF to the dealer (Invoices → Email invoice). */
class InvoiceEmailController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice, MailService $mail)
    {
        $this->authorizeDealer($invoice->customer);
        if ($invoice->status === 'cancelled') {
            return back()->with('error', 'Cancelled invoices cannot be emailed.');
        }
        $data = $request->validate(['to' => ['required', 'email', 'max:191'], 'note' => ['nullable', 'max:1000']]);

        $pdf = app(DocumentPdfController::class)->invoice($invoice)->getContent();
        $log = $mail->send($data['to'], new InvoiceMail($invoice->load('customer'), $pdf, $data['note'] ?? null), 'invoice', $invoice);
        AuditLog::record('invoice.email', $invoice, "Invoice {$invoice->number} emailed to {$data['to']}: {$log->status}.");

        [$type, $message] = MailService::outcome($log);

        return back()->with($type, $message);
    }
}
