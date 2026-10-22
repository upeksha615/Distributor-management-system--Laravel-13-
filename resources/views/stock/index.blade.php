<x-layouts.app title="Stock levels">
    <x-page-header title="Stock levels" subtitle="On-hand quantity per warehouse">
        <x-export-buttons />
        @if (auth()->user()->can('stock.adjust'))
            <a href="{{ route('stock.adjust') }}" class="btn btn-secondary">Adjust stock</a>
            <a href="{{ route('stock.transfer') }}" class="btn btn-secondary">Transfer</a>
        @endif
    </x-page-header>

    <x-search-bar placeholder="SKU or name" />

    <div class="card overflow-x-auto">
        <table class="table">
            <thead>
            <tr>
                <th>SKU</th><th>Name</th>
                @foreach ($warehouses as $w)<th class="num">{{ $w->name }}</th>@endforeach
                <th class="num">Total</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($products as $p)
                @php $total = $p->stockLevels->sum('quantity'); @endphp
                <tr>
                    <td><a href="{{ route('products.show', $p) }}" class="link">{{ $p->sku }}</a></td>
                    <td>{{ $p->name }}</td>
                    @foreach ($warehouses as $w)<td class="num">{{ qty($p->stockIn($w->id)) }}</td>@endforeach
                    <td class="num font-semibold {{ $p->reorder_level && $total <= $p->reorder_level ? 'text-red-600' : '' }}">{{ qty($total) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $warehouses->count() + 3 }}" class="py-6 text-center text-slate-500">No products found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
</x-layouts.app>
