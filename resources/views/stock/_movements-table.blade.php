@php
    $refRoutes = [
        \App\Models\Invoice::class => 'invoices.show',
        \App\Models\GoodsReceipt::class => 'goods-receipts.show',
        \App\Models\CreditNote::class => 'credit-notes.show',
    ];
@endphp
<div class="overflow-x-auto">
    <table class="table">
        <thead>
        <tr>
            <th>Date</th>
            @if ($showProduct ?? true)<th>Product</th>@endif
            <th>Warehouse</th><th>Type</th><th>Reference</th><th class="num">Qty</th><th class="num">Balance</th><th>By</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($movements as $m)
            <tr>
                <td class="whitespace-nowrap">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                @if ($showProduct ?? true)
                    <td><a href="{{ route('products.show', $m->product_id) }}" class="link">{{ $m->product->sku }}</a> <span class="text-slate-500">{{ $m->product->name }}</span></td>
                @endif
                <td>{{ $m->warehouse->name }}</td>
                <td>{{ str_replace('_', ' ', ucfirst($m->type)) }}</td>
                <td>
                    @if ($m->reference && isset($refRoutes[$m->reference_type]))
                        <a href="{{ route($refRoutes[$m->reference_type], $m->reference_id) }}" class="link">{{ $m->reference->number }}</a>
                    @else
                        <span class="text-slate-500">{{ $m->note }}</span>
                    @endif
                </td>
                <td class="num {{ $m->quantity < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ qty($m->quantity) }}</td>
                <td class="num">{{ qty($m->balance_after) }}</td>
                <td class="text-slate-500">{{ $m->user?->name }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="py-6 text-center text-slate-500">No stock movements.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
