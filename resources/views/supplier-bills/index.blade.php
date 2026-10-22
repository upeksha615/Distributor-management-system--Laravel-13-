<x-layouts.app title="Supplier bills">
    <x-page-header title="Supplier bills" subtitle="What we owe suppliers. Bills for goods are created automatically when goods are received.">
        <x-export-buttons />
        <a href="{{ route('supplier-payments.create') }}" class="btn btn-secondary">Make payment</a>
        <a href="{{ route('supplier-bills.create') }}" class="btn btn-primary">New bill</a>
    </x-page-header>

    <x-search-bar placeholder="Bill no, supplier invoice or description">
        <x-select label="Supplier" name="supplier_id" :options="$suppliers" :value="request('supplier_id')" placeholder="All" class="w-48" />
        <x-select label="Status" name="status" :value="request('status')" placeholder="All" class="w-36"
                  :options="['unpaid' => 'Unpaid', 'partial' => 'Partial', 'overdue' => 'Overdue', 'paid' => 'Paid', 'cancelled' => 'Cancelled']" />
        <x-field label="From" name="from" type="date" :value="request('from')" class="w-40" />
        <x-field label="To" name="to" type="date" :value="request('to')" class="w-40" />
    </x-search-bar>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Bill</th><th>Date</th><th>Supplier</th><th>Supplier inv</th><th>Description</th><th>Due</th><th>Status</th><th class="num">Amount</th><th class="num">Balance</th></tr></thead>
            <tbody>
            @forelse ($bills as $bill)
                <tr>
                    <td><a href="{{ route('supplier-bills.show', $bill) }}" class="link">{{ $bill->number }}</a></td>
                    <td>{{ $bill->bill_date->format('d/m/Y') }}</td>
                    <td>{{ $bill->supplier->name }}</td>
                    <td>{{ $bill->supplier_invoice_no ?: '-' }}</td>
                    <td class="text-slate-600">{{ $bill->description }}</td>
                    <td class="{{ in_array($bill->status, ['unpaid', 'partial']) && $bill->daysOverdue() ? 'text-red-600' : '' }}">{{ $bill->due_date->format('d/m/Y') }}</td>
                    <td><x-status :status="$bill->status" /></td>
                    <td class="num">{{ money($bill->amount, false) }}</td>
                    <td class="num">{{ $bill->status === 'cancelled' ? '-' : money($bill->balance(), false) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-6 text-center text-slate-500">No supplier bills found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $bills->links() }}</div>
</x-layouts.app>
