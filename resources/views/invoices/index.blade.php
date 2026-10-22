<x-layouts.app title="Invoices">
    <x-page-header title="Invoices">
        <x-export-buttons />
        <a href="{{ route('invoices.create') }}" class="btn btn-primary">New invoice</a>
    </x-page-header>

    <x-search-bar placeholder="Invoice number or customer">
        <x-select label="Status" name="status" :value="request('status')" placeholder="All" class="w-36"
                  :options="['unpaid' => 'Unpaid', 'partial' => 'Partial', 'overdue' => 'Overdue', 'paid' => 'Paid', 'cancelled' => 'Cancelled']" />
        <x-field label="From" name="from" type="date" :value="request('from')" class="w-40" />
        <x-field label="To" name="to" type="date" :value="request('to')" class="w-40" />
    </x-search-bar>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Due</th><th>Status</th><th class="num">Total</th><th class="num">Balance</th></tr></thead>
            <tbody>
            @forelse ($invoices as $inv)
                <tr>
                    <td><a href="{{ route('invoices.show', $inv) }}" class="link">{{ $inv->number }}</a></td>
                    <td>{{ $inv->invoice_date->format('d/m/Y') }}</td>
                    <td>{{ $inv->customer->name }}</td>
                    <td class="{{ in_array($inv->status, ['unpaid', 'partial']) && $inv->daysOverdue() ? 'text-red-600' : '' }}">{{ $inv->due_date->format('d/m/Y') }}</td>
                    <td><x-status :status="$inv->status" /></td>
                    <td class="num">{{ money($inv->total, false) }}</td>
                    <td class="num">{{ $inv->status === 'cancelled' ? '-' : money($inv->balance(), false) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-500">No invoices found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.app>
