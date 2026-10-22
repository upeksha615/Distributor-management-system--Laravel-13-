<x-layouts.app :title="$quote->number">
    <x-page-header :title="'Quotation '.$quote->number">
        @if ($quote->status === 'open')
            <a href="{{ route('quotations.edit', $quote) }}" class="btn btn-secondary">Edit</a>
        @endif
        @if (in_array($quote->status, ['open', 'rejected']))
            <form method="POST" action="{{ route('quotations.status', $quote) }}">@csrf<input type="hidden" name="status" value="accepted"><button class="btn btn-secondary">Mark accepted</button></form>
        @endif
        @if (in_array($quote->status, ['open', 'accepted']))
            <form method="POST" action="{{ route('quotations.status', $quote) }}" onsubmit="return confirm('Mark this quotation as rejected?')">@csrf<input type="hidden" name="status" value="rejected"><button class="btn btn-danger">Mark rejected</button></form>
        @endif
        @if ($quote->salesOrder)
            <a href="{{ route('sales-orders.show', $quote->salesOrder) }}" class="btn btn-secondary">View order {{ $quote->salesOrder->number }}</a>
        @endif
        @if ($quote->invoice)
            <a href="{{ route('invoices.show', $quote->invoice) }}" class="btn btn-secondary">View invoice {{ $quote->invoice->number }}</a>
        @endif
        <a href="{{ route('quotations.pdf', $quote) }}" class="btn btn-secondary">Download PDF</a>
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </x-page-header>

    @if ($quote->isExpired())
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 no-print">This quotation <strong>expired</strong> on {{ $quote->valid_until->format('d/m/Y') }}. Check prices before converting it.</div>
    @endif

    @if (in_array($quote->status, ['open', 'accepted']))
        <div class="card card-body mb-6 flex flex-wrap items-end gap-3 no-print">
            <form method="POST" action="{{ route('quotations.convert-order', $quote) }}" onsubmit="return confirm('Create a sales order from this quotation?')">
                @csrf
                <button class="btn btn-secondary">Convert to sales order</button>
            </form>
            <form method="POST" action="{{ route('quotations.convert-invoice', $quote) }}" class="flex flex-wrap items-end gap-3" onsubmit="return confirm('Create an invoice dated today from this quotation? Stock will be deducted.')">
                @csrf
                @if (auth()->user()->can('invoices.override_credit'))
                    <x-checkbox label="Override credit limit / hold" name="override_credit" class="mb-2" />
                @endif
                <button class="btn btn-success">Convert to invoice</button>
            </form>
            <p class="w-full text-xs text-slate-500">Converting to an invoice uses today's date, deducts stock from {{ $quote->warehouse->name }} and checks the customer's credit limit.</p>
        </div>
    @endif

    <div class="card card-body">
        @include('partials.document-header', ['docTitle' => 'QUOTATION', 'docNumber' => $quote->number])
        <div class="mb-6 grid gap-6 text-sm sm:grid-cols-2">
            <div>
                <div class="text-xs font-semibold uppercase text-slate-500">Customer</div>
                <div class="font-medium"><a href="{{ route('customers.show', $quote->customer) }}" class="link">{{ $quote->customer->name }}</a></div>
                <div>{{ $quote->customer->address }} {{ $quote->customer->city }}</div>
            </div>
            <dl class="grid grid-cols-2 gap-1 sm:justify-self-end">
                <dt class="text-slate-500">Date</dt><dd>{{ $quote->quote_date->format('d/m/Y') }}</dd>
                <dt class="text-slate-500">Valid until</dt><dd>{{ $quote->valid_until?->format('d/m/Y') ?? '-' }}</dd>
                <dt class="text-slate-500">Warehouse</dt><dd>{{ $quote->warehouse->name }}</dd>
                <dt class="text-slate-500">Status</dt><dd>@include('quotations._status', ['quote' => $quote])</dd>
                <dt class="text-slate-500">Prepared by</dt><dd>{{ $quote->user?->name }}</dd>
            </dl>
        </div>

        <table class="table">
            <thead><tr><th>#</th><th>SKU</th><th>Description</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Disc %</th><th class="num">Total</th></tr></thead>
            <tbody>
            @foreach ($quote->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->product->sku }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td class="num">{{ qty($item->quantity) }}</td>
                    <td class="num">{{ money($item->unit_price, false) }}</td>
                    <td class="num">{{ $item->discount_percent > 0 ? qty($item->discount_percent) : '' }}</td>
                    <td class="num">{{ money($item->line_total, false) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @include('partials.totals', ['doc' => $quote])
        @if ($quote->notes)
            <p class="mt-4 text-sm text-slate-600"><span class="font-semibold">Notes:</span> {{ $quote->notes }}</p>
        @endif
    </div>
</x-layouts.app>
