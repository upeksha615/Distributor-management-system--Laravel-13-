<x-layouts.app title="Credit notes">
    <x-page-header title="Credit notes" subtitle="Create a credit note from the invoice being returned">
        <x-export-buttons />
    </x-page-header>

    <x-search-bar placeholder="Credit note number or customer" />

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Credit note</th><th>Date</th><th>Customer</th><th>Invoice</th><th>Reason</th><th>Restocked</th><th class="num">Amount</th></tr></thead>
            <tbody>
            @forelse ($notes as $cn)
                <tr>
                    <td><a href="{{ route('credit-notes.show', $cn) }}" class="link">{{ $cn->number }}</a></td>
                    <td>{{ $cn->credit_date->format('d/m/Y') }}</td>
                    <td>{{ $cn->customer->name }}</td>
                    <td><a href="{{ route('invoices.show', $cn->invoice) }}" class="link">{{ $cn->invoice->number }}</a></td>
                    <td>{{ $cn->reason }}</td>
                    <td>{{ $cn->restock ? 'Yes' : 'No' }}</td>
                    <td class="num">{{ money($cn->total, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-500">No credit notes yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $notes->links() }}</div>
</x-layouts.app>
