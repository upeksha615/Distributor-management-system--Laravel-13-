<x-layouts.app :title="$invoice->number">
    <x-page-header :title="'Invoice '.$invoice->number">
        @if (in_array($invoice->status, ['unpaid', 'partial']))
            <a href="{{ route('payments.create', ['customer_id' => $invoice->customer_id]) }}" class="btn btn-success">Receive payment</a>
        @endif
        @if ($invoice->status !== 'cancelled')
            <a href="{{ route('credit-notes.create', $invoice) }}" class="btn btn-secondary">Return / credit note</a>
        @endif
        @if ($invoice->status === 'unpaid' && $invoice->creditNotes->isEmpty() && $invoice->allocations->isEmpty() && auth()->user()->can('invoices.cancel'))
            <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" onsubmit="return confirm('Cancel this invoice and return the stock?')">@csrf<button class="btn btn-danger">Cancel invoice</button></form>
        @endif
        <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-secondary">Download PDF</a>
        @if ($invoice->status !== 'cancelled' && auth()->user()->can('invoices.email'))
            <div x-data="{ open: false }" class="relative">
                <button type="button" class="btn btn-secondary" @click="open = ! open">Email invoice</button>
                <form x-show="open" x-cloak @click.outside="open = false" method="POST" action="{{ route('invoices.email', $invoice) }}"
                      class="absolute right-0 z-20 mt-2 w-80 space-y-3 rounded-md border border-slate-200 bg-white p-4 text-left shadow-lg">
                    @csrf
                    <x-field label="To" name="to" type="email" :value="$invoice->customer->email" required />
                    <x-field label="Message (optional)" name="note" type="textarea" />
                    <button class="btn btn-primary w-full">Send with PDF attached</button>
                </form>
            </div>
        @endif
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </x-page-header>

    <div class="card card-body">
        @include('partials.document-header', ['docTitle' => $invoice->tax > 0 ? 'TAX INVOICE' : 'INVOICE', 'docNumber' => $invoice->number])

        <div class="mb-6 grid gap-6 text-sm sm:grid-cols-2">
            <div>
                <div class="text-xs font-semibold uppercase text-slate-500">Bill to</div>
                <div class="font-medium"><a href="{{ route('customers.show', $invoice->customer) }}" class="link">{{ $invoice->customer->name }}</a></div>
                <div>{{ $invoice->customer->address }} {{ $invoice->customer->city }}</div>
                <div>{{ $invoice->customer->phone }}</div>
                @if ($invoice->customer->tax_number)<div>Tax no: {{ $invoice->customer->tax_number }}</div>@endif
            </div>
            <dl class="grid grid-cols-2 gap-1 sm:justify-self-end">
                <dt class="text-slate-500">Invoice date</dt><dd>{{ $invoice->invoice_date->format('d/m/Y') }}</dd>
                <dt class="text-slate-500">Due date</dt><dd>{{ $invoice->due_date->format('d/m/Y') }}</dd>
                <dt class="text-slate-500">Customer code</dt><dd>{{ $invoice->customer->code }}</dd>
                @if ($invoice->salesOrder)<dt class="text-slate-500">Order</dt><dd><a href="{{ route('sales-orders.show', $invoice->salesOrder) }}" class="link">{{ $invoice->salesOrder->number }}</a></dd>@endif
                <dt class="text-slate-500">Status</dt><dd><x-status :status="$invoice->status" /></dd>
            </dl>
        </div>

        <table class="table">
            <thead><tr><th>#</th><th>SKU</th><th>Description</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Disc %</th><th class="num">Total</th></tr></thead>
            <tbody>
            @foreach ($invoice->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->product->sku }}</td>
                    <td>{{ $item->product->name }}
                        @if ($item->returned_quantity > 0)<span class="no-print text-xs text-amber-700">({{ qty($item->returned_quantity) }} returned)</span>@endif
                    </td>
                    <td class="num">{{ qty($item->quantity) }}</td>
                    <td class="num">{{ money($item->unit_price, false) }}</td>
                    <td class="num">{{ $item->discount_percent > 0 ? qty($item->discount_percent) : '' }}</td>
                    <td class="num">{{ money($item->line_total, false) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        @include('partials.totals', ['doc' => $invoice])
        @if ($invoice->status !== 'cancelled')
            <div class="mt-1 flex justify-end">
                <dl class="w-full max-w-xs space-y-1 text-sm">
                    @if ($invoice->credited_amount > 0)
                        <div class="flex justify-between"><dt class="text-slate-500">Credit notes</dt><dd class="tabular-nums">-{{ money($invoice->credited_amount, false) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-slate-500">Paid</dt><dd class="tabular-nums">-{{ money($invoice->paid_amount, false) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-1 font-semibold"><dt>Balance due</dt><dd class="tabular-nums">{{ money($invoice->balance()) }}</dd></div>
                </dl>
            </div>
        @endif
        @if ($invoice->notes)<p class="mt-4 text-sm"><span class="text-slate-500">Notes:</span> {{ $invoice->notes }}</p>@endif

        <div class="mt-16 hidden grid-cols-2 gap-16 text-center text-sm print:grid">
            <div class="border-t border-slate-400 pt-1">Authorised signature</div>
            <div class="border-t border-slate-400 pt-1">Received in good condition</div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2 no-print">
        <div class="card overflow-x-auto">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Payments applied</h2>
            <table class="table">
                <thead><tr><th>Receipt</th><th>Date</th><th>Status</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @forelse ($invoice->allocations as $a)
                    <tr>
                        <td><a href="{{ route('payments.show', $a->payment) }}" class="link">{{ $a->payment->number }}</a></td>
                        <td>{{ $a->payment->payment_date->format('d/m/Y') }}</td>
                        <td><x-status :status="$a->payment->status" /></td>
                        <td class="num">{{ money($a->amount, false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-slate-500">No payments yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card overflow-x-auto">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Credit notes</h2>
            <table class="table">
                <thead><tr><th>Credit note</th><th>Date</th><th>Reason</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @forelse ($invoice->creditNotes as $cn)
                    <tr>
                        <td><a href="{{ route('credit-notes.show', $cn) }}" class="link">{{ $cn->number }}</a></td>
                        <td>{{ $cn->credit_date->format('d/m/Y') }}</td>
                        <td>{{ $cn->reason }}</td>
                        <td class="num">{{ money($cn->total, false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-slate-500">No credit notes.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
