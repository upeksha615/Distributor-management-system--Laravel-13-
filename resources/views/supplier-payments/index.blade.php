<x-layouts.app title="Supplier payments">
    <x-page-header title="Payments to suppliers">
        <x-export-buttons />
        <a href="{{ route('supplier-payments.create') }}" class="btn btn-primary">Make payment</a>
    </x-page-header>

    <x-search-bar placeholder="Voucher, cheque no, reference or supplier">
        <x-select label="Supplier" name="supplier_id" :options="$suppliers" :value="request('supplier_id')" placeholder="All" class="w-48" />
        <x-select label="Method" name="method" :options="\App\Models\Payment::METHODS" :value="request('method')" placeholder="All" class="w-36" />
        <x-field label="From" name="from" type="date" :value="request('from')" class="w-40" />
        <x-field label="To" name="to" type="date" :value="request('to')" class="w-40" />
    </x-search-bar>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Voucher</th><th>Date</th><th>Supplier</th><th>Method</th><th>Cheque / ref</th><th class="num">Amount</th></tr></thead>
            <tbody>
            @forelse ($payments as $p)
                <tr>
                    <td><a href="{{ route('supplier-payments.show', $p) }}" class="link">{{ $p->number }}</a></td>
                    <td>{{ $p->payment_date->format('d/m/Y') }}</td>
                    <td>{{ $p->supplier->name }}</td>
                    <td>{{ $p->methodLabel() }}</td>
                    <td>{{ $p->method === 'cheque' ? $p->cheque_number.' · '.$p->cheque_date?->format('d/m/Y') : $p->reference }}</td>
                    <td class="num">{{ money($p->amount, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-slate-500">No supplier payments found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</x-layouts.app>
