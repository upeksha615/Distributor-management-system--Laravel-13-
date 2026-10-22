<?php

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\SalesService;
use App\Support\Exporter;
use Illuminate\Http\Request;

class CreditNoteController extends Controller
{
    public function __construct(private SalesService $sales) {}

    public function index(Request $request)
    {
        $notes = $this->dealerScope(CreditNote::with(['customer', 'invoice']))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w->where('number', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->search($term))))
            ->latest('id');

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'credit-notes', 'Credit notes', [
                'Credit note' => 'text', 'Date' => 'text', 'Customer' => 'text', 'Invoice' => 'text', 'Reason' => 'text', 'Restocked' => 'text', 'Amount' => 'money',
            ], $rows = $notes->get()->map(fn ($n) => [
                $n->number, $n->credit_date->format('d/m/Y'), $n->customer->name, $n->invoice->number, $n->reason, $n->restock ? 'Yes' : 'No', $n->total,
            ]), $this->filterSummary($request), ['Total', null, null, null, null, null, $rows->sum(fn ($r) => $r[6])]);
        }

        $notes = $notes->paginate(25)->withQueryString();

        return view('credit-notes.index', compact('notes'));
    }

    public function create(Invoice $invoice)
    {
        $this->authorizeDealer($invoice->customer);
        abort_if($invoice->status === 'cancelled', 403, 'Cannot credit a cancelled invoice.');
        $invoice->load(['customer', 'items.product']);

        return view('credit-notes.create', compact('invoice'));
    }

    public function store(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'credit_date' => ['required', 'date'],
            'reason' => ['required', 'max:255'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
        ]) + ['restock' => $request->boolean('restock')];

        $note = $this->sales->createCreditNote($invoice, $data['quantities'], $data);

        return redirect()->route('credit-notes.show', $note)->with('success', "Credit note {$note->number} created.");
    }

    public function show(CreditNote $creditNote)
    {
        $this->authorizeDealer($creditNote->customer);
        $creditNote->load(['customer', 'invoice', 'warehouse', 'user', 'items.product']);

        return view('credit-notes.show', ['note' => $creditNote]);
    }
}
