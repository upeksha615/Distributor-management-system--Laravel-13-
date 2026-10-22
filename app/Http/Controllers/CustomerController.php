<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::with('salesRep')->visibleTo($request->user())
            ->search($request->q)
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false), fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'on_hold', fn ($q) => $q->where('on_hold', true))
            ->withSum(['invoices as balance' => fn ($q) => $q->where('status', '!=', 'cancelled')], DB::raw('total - paid_amount - credited_amount'))
            ->orderBy('name');

        if ($format = $this->exportFormat($request)) {
            return Exporter::download($format, 'customers', 'Customers', [
                'Code' => 'text', 'Name' => 'text', 'Contact person' => 'text', 'Phone' => 'text', 'Email' => 'text', 'Address' => 'text',
                'City' => 'text', 'Tax number' => 'text', 'Sales rep' => 'text', 'Credit limit' => 'money', 'Terms (days)' => 'number',
                'Balance' => 'money', 'On hold' => 'text',
            ], $rows = $customers->get()->map(fn ($c) => [
                $c->code, $c->name, $c->contact_person, $c->phone, $c->email, $c->address, $c->city, $c->tax_number,
                $c->salesRep?->name, $c->credit_limit, $c->payment_terms_days, $c->balance ?? 0, $c->on_hold ? 'Yes' : 'No',
            ]), $this->filterSummary($request), ['Total', null, null, null, null, null, null, null, null, null, null, $rows->sum(fn ($r) => $r[11]), null]);
        }

        $customers = $customers->paginate(25)->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function show(Customer $customer)
    {
        $this->authorizeDealer($customer);
        $customer->load('salesRep');

        return view('customers.show', [
            'customer' => $customer,
            'balance' => $customer->outstandingBalance(),
            'unallocated' => $customer->unallocatedCredit(),
            'openInvoices' => $customer->invoices()->open()->orderBy('due_date')->get(),
            'recentPayments' => $customer->payments()->latest('id')->limit(10)->get(),
            'recentOrders' => $customer->salesOrders()->latest('id')->limit(10)->get(),
        ]);
    }

    /** Statement of account: opening balance, every transaction in the period, running balance. */
    public function statement(Request $request, Customer $customer)
    {
        $this->authorizeDealer($customer);
        $from = Carbon::parse($request->input('from', today()->subMonths(3)->startOfMonth()))->startOfDay();
        $to = Carbon::parse($request->input('to', today()))->endOfDay();

        $entries = collect();

        foreach ($customer->invoices()->where('status', '!=', 'cancelled')->get() as $inv) {
            $entries->push(['date' => $inv->invoice_date, 'type' => 'Invoice', 'number' => $inv->number, 'url' => route('invoices.show', $inv), 'debit' => (float) $inv->total, 'credit' => 0]);
        }
        foreach ($customer->creditNotes()->get() as $cn) {
            $entries->push(['date' => $cn->credit_date, 'type' => 'Credit note', 'number' => $cn->number, 'url' => route('credit-notes.show', $cn), 'debit' => 0, 'credit' => (float) $cn->total]);
        }
        foreach ($customer->payments()->whereIn('status', ['verified', 'reversed'])->get() as $p) {
            // A receipt was counted if it settled: verified non-cheque, or a cheque that cleared (or was verified under the on-verification policy).
            $counted = $p->method !== 'cheque'
                ? $p->verified_at !== null
                : ($p->cleared_date !== null || (config('distributor.cheque_recognition') === 'on_verification' && $p->verified_at !== null));
            if (! $counted) {
                continue;
            }
            $entries->push(['date' => $p->method === 'cheque' && $p->cleared_date && config('distributor.cheque_recognition') === 'on_clearance' ? $p->cleared_date : $p->payment_date,
                'type' => 'Payment ('.$p->methodLabel().')', 'number' => $p->number, 'url' => route('payments.show', $p), 'debit' => 0, 'credit' => (float) $p->amount]);
            if ($p->status === 'reversed') {
                $entries->push(['date' => $p->returned_date ?? $p->reversed_at?->startOfDay() ?? $p->payment_date, 'type' => $p->cheque_status === 'returned' ? 'Returned cheque' : 'Payment reversed',
                    'number' => $p->number, 'url' => route('payments.show', $p), 'debit' => (float) $p->amount, 'credit' => 0]);
            }
        }

        $entries = $entries->sortBy(fn ($e) => $e['date']->format('Ymd').$e['number'])->values();
        $opening = $entries->filter(fn ($e) => $e['date']->lt($from))->sum(fn ($e) => $e['debit'] - $e['credit']);

        $running = $opening;
        $lines = $entries->filter(fn ($e) => $e['date']->between($from, $to))->map(function ($e) use (&$running) {
            $running += $e['debit'] - $e['credit'];

            return $e + ['balance' => $running];
        });

        if ($format = $this->exportFormat($request)) {
            $period = $from->format('d/m/Y').' - '.$to->format('d/m/Y');
            $columns = ['Date' => 'text', 'Type' => 'text', 'Reference' => 'text', 'Debit' => 'money', 'Credit' => 'money', 'Balance' => 'money'];
            $rows = collect([[$from->format('d/m/Y'), 'Opening balance', '', null, null, $opening]])
                ->concat($lines->map(fn ($l) => [$l['date']->format('d/m/Y'), $l['type'], $l['number'], $l['debit'] ?: null, $l['credit'] ?: null, $l['balance']]));
            $name = 'statement-'.$customer->code;

            if ($format === 'pdf') {
                return Exporter::pdfView('pdf.document', [
                    'docTitle' => 'STATEMENT OF ACCOUNT',
                    'docNumber' => $period,
                    'party' => ['label' => 'Customer', 'lines' => [$customer->name.' ('.$customer->code.')', trim($customer->address.' '.$customer->city), $customer->phone]],
                    'meta' => ['Statement date' => today()->format('d/m/Y'), 'Terms' => $customer->payment_terms_days.' days'],
                    'columns' => $columns,
                    'rows' => $rows->all(),
                    'totals' => ['Closing balance' => $running],
                ], $name);
            }

            return Exporter::download($format, $name, 'Statement - '.$customer->name, $columns, $rows, $period,
                ['Closing balance', null, null, null, null, $running]);
        }

        return view('customers.statement', compact('customer', 'from', 'to', 'opening', 'lines', 'running'));
    }

    public function create()
    {
        return view('customers.form', [
            'customer' => new Customer(['is_active' => true, 'payment_terms_days' => 30, 'price_level' => 'wholesale', 'code' => $this->nextCode()]),
            'reps' => $this->reps(),
        ]);
    }

    public function store(Request $request)
    {
        $customer = Customer::create($this->validated($request));

        return redirect()->route('customers.show', $customer)->with('success', 'Customer created.');
    }

    public function edit(Customer $customer)
    {
        $this->authorizeDealer($customer);
        return view('customers.form', ['customer' => $customer, 'reps' => $this->reps()]);
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizeDealer($customer);
        $customer->update($this->validated($request, $customer));

        return redirect()->route('customers.show', $customer)->with('success', 'Customer updated.');
    }

    private function reps()
    {
        return User::where('is_active', true)->whereIn('role', ['sales', 'manager', 'admin'])->orderBy('name')->get();
    }

    private function nextCode(): string
    {
        return 'C'.str_pad((string) ((Customer::max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'max:30', Rule::unique('customers')->ignore($customer)],
            'name' => ['required', 'max:255'],
            'contact_person' => ['nullable', 'max:255'],
            'phone' => ['nullable', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'max:255'],
            'city' => ['nullable', 'max:100'],
            'tax_number' => ['nullable', 'max:50'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'price_level' => ['required', 'in:wholesale,retail'],
            'sales_rep_id' => ['nullable', 'exists:users,id'],
        ]) + ['is_active' => $request->boolean('is_active')];

        // Only managers can change credit controls.
        if ($request->user()->can('customers.credit')) {
            $data['on_hold'] = $request->boolean('on_hold');
        } elseif ($customer) {
            $data['credit_limit'] = $customer->credit_limit;
        }

        return $data;
    }
}
