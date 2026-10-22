<x-layouts.app :title="$customer->name">
    <x-page-header :title="$customer->name" :subtitle="$customer->code.($customer->city ? ' · '.$customer->city : '')">
        <a href="{{ route('invoices.create', ['customer_id' => $customer->id]) }}" class="btn btn-primary">New invoice</a>
        <a href="{{ route('sales-orders.create', ['customer_id' => $customer->id]) }}" class="btn btn-secondary">New order</a>
        <a href="{{ route('quotations.create', ['customer_id' => $customer->id]) }}" class="btn btn-secondary">New quotation</a>
        <a href="{{ route('payments.create', ['customer_id' => $customer->id]) }}" class="btn btn-secondary">Receive payment</a>
        <a href="{{ route('customers.statement', $customer) }}" class="btn btn-secondary">Statement</a>
        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-secondary">Edit</a>
    </x-page-header>

    @if ($customer->on_hold)
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">This customer is <strong>on hold</strong>. New invoices need a manager override.</div>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Balance owing" :value="money($balance)" :tone="$balance > 0 ? 'red' : 'green'" />
        <x-stat label="Credit limit" :value="$customer->credit_limit > 0 ? money($customer->credit_limit) : 'No limit'" />
        <x-stat label="Available credit" :value="$customer->credit_limit > 0 ? money($customer->credit_limit - $balance) : '-'" :tone="$customer->credit_limit > 0 && $balance > $customer->credit_limit ? 'red' : 'slate'" />
        <x-stat label="Unallocated payments" :value="money($unallocated)" :tone="$unallocated > 0 ? 'amber' : 'slate'" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card card-body">
            <dl class="space-y-2 text-sm">
                <div><dt class="text-slate-500">Contact</dt><dd>{{ $customer->contact_person ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Phone</dt><dd>{{ $customer->phone ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Email</dt><dd>{{ $customer->email ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Address</dt><dd>{{ $customer->address ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Tax number</dt><dd>{{ $customer->tax_number ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Payment terms</dt><dd>{{ $customer->payment_terms_days }} days</dd></div>
                <div><dt class="text-slate-500">Sales rep</dt><dd>{{ $customer->salesRep?->name ?? '-' }}</dd></div>
            </dl>
        </div>

        <div class="card lg:col-span-2 overflow-x-auto">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Open invoices</h2>
            <table class="table">
                <thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th class="num">Days late</th><th class="num">Total</th><th class="num">Balance</th></tr></thead>
                <tbody>
                @forelse ($openInvoices as $inv)
                    <tr>
                        <td><a href="{{ route('invoices.show', $inv) }}" class="link">{{ $inv->number }}</a></td>
                        <td>{{ $inv->invoice_date->format('d/m/Y') }}</td>
                        <td>{{ $inv->due_date->format('d/m/Y') }}</td>
                        <td class="num {{ $inv->daysOverdue() ? 'text-red-600' : '' }}">{{ $inv->daysOverdue() ?: '-' }}</td>
                        <td class="num">{{ money($inv->total, false) }}</td>
                        <td class="num font-medium">{{ money($inv->balance(), false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-500">No open invoices.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card overflow-x-auto">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Recent payments</h2>
            <table class="table">
                <thead><tr><th>Receipt</th><th>Date</th><th>Method</th><th>Status</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @forelse ($recentPayments as $p)
                    <tr>
                        <td><a href="{{ route('payments.show', $p) }}" class="link">{{ $p->number }}</a></td>
                        <td>{{ $p->payment_date->format('d/m/Y') }}</td>
                        <td>{{ $p->methodLabel() }}</td>
                        <td><x-status :status="$p->status" /></td>
                        <td class="num">{{ money($p->amount, false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-500">No payments yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card overflow-x-auto">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Recent sales orders</h2>
            <table class="table">
                <thead><tr><th>Order</th><th>Date</th><th>Status</th><th class="num">Total</th></tr></thead>
                <tbody>
                @forelse ($recentOrders as $o)
                    <tr>
                        <td><a href="{{ route('sales-orders.show', $o) }}" class="link">{{ $o->number }}</a></td>
                        <td>{{ $o->order_date->format('d/m/Y') }}</td>
                        <td><x-status :status="$o->status" /></td>
                        <td class="num">{{ money($o->total, false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-slate-500">No orders yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
