<x-layouts.app :title="$grn->number">
    <x-page-header :title="'Goods received '.$grn->number">
        <a href="{{ route('purchase-orders.show', $grn->purchase_order_id) }}" class="btn btn-secondary">View PO</a>
        @if (auth()->user()->can('payables.view') && ($bill = \App\Models\SupplierBill::where('goods_receipt_id', $grn->id)->first()))
            <a href="{{ route('supplier-bills.show', $bill) }}" class="btn btn-secondary">View bill {{ $bill->number }}</a>
        @endif
        <a href="{{ route('goods-receipts.pdf', $grn) }}" class="btn btn-secondary">Download PDF</a>
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </x-page-header>

    <div class="card card-body">
        @include('partials.document-header', ['docTitle' => 'GOODS RECEIVED NOTE', 'docNumber' => $grn->number])

        <dl class="mb-6 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-4">
            <dt class="text-slate-500">Supplier</dt><dd>{{ $grn->purchaseOrder->supplier->name }}</dd>
            <dt class="text-slate-500">Received date</dt><dd>{{ $grn->received_date->format('d/m/Y') }}</dd>
            <dt class="text-slate-500">Purchase order</dt><dd>{{ $grn->purchaseOrder->number }}</dd>
            <dt class="text-slate-500">Supplier invoice</dt><dd>{{ $grn->supplier_invoice_no ?: '-' }}</dd>
            <dt class="text-slate-500">Warehouse</dt><dd>{{ $grn->warehouse->name }}</dd>
            <dt class="text-slate-500">Received by</dt><dd>{{ $grn->user?->name }}</dd>
        </dl>

        <table class="table">
            <thead><tr><th>#</th><th>SKU</th><th>Description</th><th class="num">Qty</th><th class="num">Unit cost</th><th class="num">Value</th></tr></thead>
            <tbody>
            @foreach ($grn->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->product->sku }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td class="num">{{ qty($item->quantity) }}</td>
                    <td class="num">{{ money($item->unit_cost, false) }}</td>
                    <td class="num">{{ money($item->quantity * $item->unit_cost, false) }}</td>
                </tr>
            @endforeach
            <tr class="font-semibold"><td colspan="5" class="text-right">Total</td><td class="num">{{ money($grn->total) }}</td></tr>
            </tbody>
        </table>
        @if ($grn->notes)<p class="mt-4 text-sm"><span class="text-slate-500">Notes:</span> {{ $grn->notes }}</p>@endif

        <div class="mt-16 hidden grid-cols-2 gap-16 text-center text-sm print:grid">
            <div class="border-t border-slate-400 pt-1">Received by</div>
            <div class="border-t border-slate-400 pt-1">Checked by</div>
        </div>
    </div>
</x-layouts.app>
