<x-layouts.app :title="$note->number">
    <x-page-header :title="'Credit note '.$note->number">
        <a href="{{ route('invoices.show', $note->invoice) }}" class="btn btn-secondary">View invoice</a>
        <a href="{{ route('credit-notes.pdf', $note) }}" class="btn btn-secondary">Download PDF</a>
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </x-page-header>

    <div class="card card-body">
        @include('partials.document-header', ['docTitle' => 'CREDIT NOTE', 'docNumber' => $note->number])

        <div class="mb-6 grid gap-6 text-sm sm:grid-cols-2">
            <div>
                <div class="text-xs font-semibold uppercase text-slate-500">Customer</div>
                <div class="font-medium">{{ $note->customer->name }} ({{ $note->customer->code }})</div>
                <div>{{ $note->customer->address }} {{ $note->customer->city }}</div>
            </div>
            <dl class="grid grid-cols-2 gap-1 sm:justify-self-end">
                <dt class="text-slate-500">Date</dt><dd>{{ $note->credit_date->format('d/m/Y') }}</dd>
                <dt class="text-slate-500">Against invoice</dt><dd>{{ $note->invoice->number }}</dd>
                <dt class="text-slate-500">Reason</dt><dd>{{ $note->reason }}</dd>
                <dt class="text-slate-500">Restocked</dt><dd>{{ $note->restock ? 'Yes, '.$note->warehouse->name : 'No' }}</dd>
            </dl>
        </div>

        <table class="table">
            <thead><tr><th>SKU</th><th>Description</th><th class="num">Qty</th><th class="num">Net price</th><th class="num">Total</th></tr></thead>
            <tbody>
            @foreach ($note->items as $item)
                <tr>
                    <td>{{ $item->product->sku }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td class="num">{{ qty($item->quantity) }}</td>
                    <td class="num">{{ money($item->unit_price, false) }}</td>
                    <td class="num">{{ money($item->line_total, false) }}</td>
                </tr>
            @endforeach
            <tr class="font-semibold"><td colspan="4" class="text-right">Credit total (incl. invoice discount/tax share)</td><td class="num">{{ money($note->total) }}</td></tr>
            </tbody>
        </table>
    </div>
</x-layouts.app>
