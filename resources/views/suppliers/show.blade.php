<x-layouts.app :title="$supplier->name">
    @php $payables = auth()->user()->can('payables.view'); @endphp
    <x-page-header :title="$supplier->name" :subtitle="$supplier->code">
        <a href="{{ route('purchase-orders.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-primary">New purchase order</a>
        @if ($payables)
            <a href="{{ route('supplier-bills.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-secondary">New bill</a>
            <a href="{{ route('supplier-payments.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-secondary">Make payment</a>
            <a href="{{ route('suppliers.statement', $supplier) }}" class="btn btn-secondary">Statement</a>
        @endif
        <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-secondary">Edit</a>
    </x-page-header>

    @if ($payables)
        @php $overdue = $openBills->filter(fn ($b) => $b->daysOverdue() > 0)->sum(fn ($b) => $b->balance()); @endphp
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat label="We owe" :value="money($balance)" :tone="$balance > 0 ? 'red' : 'green'" />
            <x-stat label="Overdue" :value="money($overdue)" :tone="$overdue > 0 ? 'red' : 'slate'" />
            <x-stat label="Open bills" :value="$openBills->count()" :href="route('supplier-bills.index', ['supplier_id' => $supplier->id])" />
            <x-stat label="Payment terms" :value="($supplier->payment_terms_days ?? 30).' days'" />
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card card-body">
            <dl class="space-y-2 text-sm">
                <div><dt class="text-slate-500">Contact</dt><dd>{{ $supplier->contact_person ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Phone</dt><dd>{{ $supplier->phone ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Email</dt><dd>{{ $supplier->email ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Address</dt><dd>{{ collect([$supplier->address, $supplier->country])->filter()->join(', ') ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Payment terms</dt><dd>{{ $supplier->payment_terms_days ?? 30 }} days</dd></div>
                <div><dt class="text-slate-500">Status</dt><dd><x-status :status="$supplier->is_active ? 'active' : 'inactive'" /></dd></div>
            </dl>
        </div>
        <div class="card lg:col-span-2 overflow-x-auto">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Purchase orders</h2>
            <table class="table">
                <thead><tr><th>PO</th><th>Date</th><th>Status</th><th class="num">Total</th></tr></thead>
                <tbody>
                @forelse ($orders as $po)
                    <tr>
                        <td><a href="{{ route('purchase-orders.show', $po) }}" class="link">{{ $po->number }}</a></td>
                        <td>{{ $po->order_date->format('d/m/Y') }}</td>
                        <td><x-status :status="$po->status" /></td>
                        <td class="num">{{ money($po->total, false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-slate-500">No purchase orders yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>

    @if ($payables)
        <div class="card mt-6 overflow-x-auto">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Open bills</h2>
            <table class="table">
                <thead><tr><th>Bill</th><th>Date</th><th>Supplier inv</th><th>Description</th><th>Due</th><th>Status</th><th class="num">Amount</th><th class="num">Balance</th></tr></thead>
                <tbody>
                @forelse ($openBills as $bill)
                    <tr>
                        <td><a href="{{ route('supplier-bills.show', $bill) }}" class="link">{{ $bill->number }}</a></td>
                        <td>{{ $bill->bill_date->format('d/m/Y') }}</td>
                        <td>{{ $bill->supplier_invoice_no ?: '-' }}</td>
                        <td class="text-slate-600">{{ $bill->description }}</td>
                        <td class="{{ $bill->daysOverdue() ? 'text-red-600' : '' }}">{{ $bill->due_date->format('d/m/Y') }}</td>
                        <td><x-status :status="$bill->status" /></td>
                        <td class="num">{{ money($bill->amount, false) }}</td>
                        <td class="num">{{ money($bill->balance(), false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-6 text-center text-slate-500">No open bills.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>
