<x-layouts.app :title="$order->number">
    <x-page-header :title="'Sales order '.$order->number">
        @if ($order->status === 'pending')
            <a href="{{ route('sales-orders.edit', $order) }}" class="btn btn-secondary">Edit</a>
            <form method="POST" action="{{ route('sales-orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order?')">@csrf<button class="btn btn-danger">Cancel order</button></form>
        @endif
        @if ($order->invoice)
            <a href="{{ route('invoices.show', $order->invoice) }}" class="btn btn-secondary">View invoice {{ $order->invoice->number }}</a>
        @endif
        <a href="{{ route('sales-orders.pdf', $order) }}" class="btn btn-secondary">Download PDF</a>
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </x-page-header>

    @if ($order->source === 'rep')
        <div class="mb-4 rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800 no-print">
            Taken by rep <strong>{{ $order->user?->name }}</strong> at the dealer on {{ $order->created_at->format('d/m/Y H:i') }}.
            @if ($order->rep_visit_id)<a href="{{ route('rep.visits.show', $order->rep_visit_id) }}" class="link">View visit</a>@endif
        </div>
    @endif

    @if ($order->status === 'pending')
        <form method="POST" action="{{ route('sales-orders.invoice', $order) }}" class="card card-body mb-6 flex flex-wrap items-end gap-3 no-print">
            @csrf
            <x-field label="Invoice date" name="invoice_date" type="date" :value="today()->format('Y-m-d')" required class="w-44" />
            @if (auth()->user()->can('invoices.override_credit'))
                <x-checkbox label="Override credit limit / hold" name="override_credit" class="mb-2" />
            @endif
            <button class="btn btn-success">Convert to invoice</button>
            <p class="w-full text-xs text-slate-500">Creating the invoice deducts stock from {{ $order->warehouse->name }} and checks the customer's credit limit.</p>
        </form>
    @endif

    <div class="card card-body">
        @include('partials.document-header', ['docTitle' => 'SALES ORDER', 'docNumber' => $order->number])
        <div class="mb-6 grid gap-6 text-sm sm:grid-cols-2">
            <div>
                <div class="text-xs font-semibold uppercase text-slate-500">Customer</div>
                <div class="font-medium"><a href="{{ route('customers.show', $order->customer) }}" class="link">{{ $order->customer->name }}</a></div>
                <div>{{ $order->customer->address }} {{ $order->customer->city }}</div>
            </div>
            <dl class="grid grid-cols-2 gap-1 sm:justify-self-end">
                <dt class="text-slate-500">Date</dt><dd>{{ $order->order_date->format('d/m/Y') }}</dd>
                <dt class="text-slate-500">Warehouse</dt><dd>{{ $order->warehouse->name }}</dd>
                <dt class="text-slate-500">Status</dt><dd><x-status :status="$order->status" /></dd>
                <dt class="text-slate-500">Taken by</dt><dd>{{ $order->user?->name }}</dd>
            </dl>
        </div>

        <table class="table">
            <thead><tr><th>#</th><th>SKU</th><th>Description</th><th class="num">Qty</th>@if ($order->status === 'pending')<th class="num no-print">In stock</th>@endif<th class="num">Price</th><th class="num">Disc %</th><th class="num">Total</th></tr></thead>
            <tbody>
            @foreach ($order->items as $i => $item)
                @php $stock = $item->product->stockIn($order->warehouse_id); @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->product->sku }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td class="num">{{ qty($item->quantity) }}</td>
                    @if ($order->status === 'pending')<td class="num no-print">@can('stock.view_qty')<span class="{{ $stock < $item->quantity ? 'font-semibold text-red-600' : '' }}">{{ qty($stock) }}</span>@else<x-stock-badge :qty="$stock" />@endcan</td>@endif
                    <td class="num">{{ money($item->unit_price, false) }}</td>
                    <td class="num">{{ $item->discount_percent > 0 ? qty($item->discount_percent) : '' }}</td>
                    <td class="num">{{ money($item->line_total, false) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @include('partials.totals', ['doc' => $order])
    </div>
</x-layouts.app>
