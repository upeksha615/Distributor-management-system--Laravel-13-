<x-layouts.app :title="$po->number">
    <x-page-header :title="'Purchase order '.$po->number">
        @if ($po->status === 'draft')
            <a href="{{ route('purchase-orders.edit', $po) }}" class="btn btn-secondary">Edit</a>
            <form method="POST" action="{{ route('purchase-orders.order', $po) }}">@csrf<button class="btn btn-primary">Mark as sent to supplier</button></form>
        @endif
        @if ($po->canReceive())
            <a href="{{ route('goods-receipts.create', $po) }}" class="btn btn-success">Receive goods</a>
        @endif
        @if (in_array($po->status, ['draft', 'ordered']))
            <form method="POST" action="{{ route('purchase-orders.cancel', $po) }}" onsubmit="return confirm('Cancel this purchase order?')">@csrf<button class="btn btn-danger">Cancel PO</button></form>
        @endif
        <a href="{{ route('purchase-orders.pdf', $po) }}" class="btn btn-secondary">Download PDF</a>
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </x-page-header>

    <div class="card card-body">
        @include('partials.document-header', ['docTitle' => 'PURCHASE ORDER', 'docNumber' => $po->number])

        <div class="mb-6 grid gap-6 text-sm sm:grid-cols-2">
            <div>
                <div class="text-xs font-semibold uppercase text-slate-500">Supplier</div>
                <div class="font-medium">{{ $po->supplier->name }}</div>
                <div>{{ $po->supplier->address }} {{ $po->supplier->country }}</div>
                <div>{{ $po->supplier->phone }} {{ $po->supplier->email }}</div>
            </div>
            <dl class="grid grid-cols-2 gap-1 sm:justify-self-end">
                <dt class="text-slate-500">Order date</dt><dd>{{ $po->order_date->format('d/m/Y') }}</dd>
                <dt class="text-slate-500">Expected</dt><dd>{{ $po->expected_date?->format('d/m/Y') ?? '-' }}</dd>
                <dt class="text-slate-500">Deliver to</dt><dd>{{ $po->warehouse->name }}</dd>
                <dt class="text-slate-500">Status</dt><dd><x-status :status="$po->status" /></dd>
            </dl>
        </div>

        <table class="table">
            <thead><tr><th>#</th><th>SKU</th><th>Description</th><th class="num">Qty</th><th class="num">Received</th><th class="num">Unit cost</th><th class="num">Total</th></tr></thead>
            <tbody>
            @foreach ($po->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->product->sku }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td class="num">{{ qty($item->quantity) }}</td>
                    <td class="num {{ $item->received_quantity >= $item->quantity ? 'text-emerald-700' : '' }}">{{ qty($item->received_quantity) }}</td>
                    <td class="num">{{ money($item->unit_cost, false) }}</td>
                    <td class="num">{{ money($item->line_total, false) }}</td>
                </tr>
            @endforeach
            <tr class="font-semibold"><td colspan="6" class="text-right">Total</td><td class="num">{{ money($po->total) }}</td></tr>
            </tbody>
        </table>
        @if ($po->notes)<p class="mt-4 text-sm"><span class="text-slate-500">Notes:</span> {{ $po->notes }}</p>@endif
    </div>

    @if ($po->receipts->isNotEmpty())
        <div class="card mt-6 no-print">
            <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Goods received</h2>
            <table class="table">
                <thead><tr><th>GRN</th><th>Date</th><th>Supplier invoice</th><th class="num">Value</th></tr></thead>
                <tbody>
                @foreach ($po->receipts as $grn)
                    <tr>
                        <td><a href="{{ route('goods-receipts.show', $grn) }}" class="link">{{ $grn->number }}</a></td>
                        <td>{{ $grn->received_date->format('d/m/Y') }}</td>
                        <td>{{ $grn->supplier_invoice_no }}</td>
                        <td class="num">{{ money($grn->total, false) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>
