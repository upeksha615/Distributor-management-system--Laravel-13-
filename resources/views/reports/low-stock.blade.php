<x-layouts.app title="Low stock">
    <x-page-header title="Low stock" subtitle="Active products at or below their reorder level (all warehouses)">
        <x-export-buttons />
        <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">New purchase order</a>
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>SKU</th><th>Name</th><th>Brand</th><th class="num">In stock</th><th class="num">Reorder level</th><th class="num">Suggested order</th><th class="num">Last cost</th></tr></thead>
            <tbody>
            @forelse ($products as $p)
                <tr>
                    <td><a href="{{ route('products.show', $p) }}" class="link">{{ $p->sku }}</a></td>
                    <td>{{ $p->name }}</td>
                    <td>{{ $p->brand?->name }}</td>
                    <td class="num font-semibold text-red-600">{{ qty($p->total_stock) }}</td>
                    <td class="num">{{ $p->reorder_level }}</td>
                    <td class="num" title="Brings stock up to twice the reorder level">{{ qty(max(0, $p->reorder_level * 2 - $p->total_stock)) }}</td>
                    <td class="num">{{ money($p->cost_price, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-500">Nothing is below its reorder level.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
