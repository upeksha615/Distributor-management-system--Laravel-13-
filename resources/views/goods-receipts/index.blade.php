<x-layouts.app title="Goods received">
    <x-page-header title="Goods received notes" subtitle="Receive goods from a purchase order's page">
        <x-export-buttons />
    </x-page-header>

    <x-search-bar placeholder="GRN or supplier invoice no" />

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>GRN</th><th>Date</th><th>PO</th><th>Supplier</th><th>Supplier invoice</th><th>Warehouse</th><th class="num">Value</th></tr></thead>
            <tbody>
            @forelse ($receipts as $grn)
                <tr>
                    <td><a href="{{ route('goods-receipts.show', $grn) }}" class="link">{{ $grn->number }}</a></td>
                    <td>{{ $grn->received_date->format('d/m/Y') }}</td>
                    <td><a href="{{ route('purchase-orders.show', $grn->purchase_order_id) }}" class="link">{{ $grn->purchaseOrder->number }}</a></td>
                    <td>{{ $grn->purchaseOrder->supplier->name }}</td>
                    <td>{{ $grn->supplier_invoice_no }}</td>
                    <td>{{ $grn->warehouse->name }}</td>
                    <td class="num">{{ money($grn->total, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-500">No goods received yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $receipts->links() }}</div>
</x-layouts.app>
