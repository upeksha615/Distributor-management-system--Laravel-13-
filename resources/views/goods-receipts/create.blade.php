<x-layouts.app :title="'Receive '.$po->number">
    <x-page-header :title="'Receive goods: '.$po->number" :subtitle="$po->supplier->name.' → '.$po->warehouse->name">
        <a href="{{ route('purchase-orders.show', $po) }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <form method="POST" action="{{ route('goods-receipts.store', $po) }}" class="space-y-4" x-data>
        @csrf
        <div class="card card-body grid gap-4 sm:grid-cols-3">
            <x-field label="Received date" name="received_date" type="date" :value="today()->format('Y-m-d')" required />
            <x-field label="Supplier invoice no" name="supplier_invoice_no" />
            <x-field label="Notes" name="notes" />
        </div>

        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>SKU</th><th>Description</th><th class="num">Ordered</th><th class="num">Already received</th><th class="num">Outstanding</th><th class="w-36 num">Receiving now</th></tr></thead>
                <tbody>
                @foreach ($po->items as $item)
                    <tr>
                        <td>{{ $item->product->sku }}</td>
                        <td>{{ $item->product->name }}</td>
                        <td class="num">{{ qty($item->quantity) }}</td>
                        <td class="num">{{ qty($item->received_quantity) }}</td>
                        <td class="num">{{ qty($item->outstanding()) }}</td>
                        <td>
                            <input type="number" step="0.01" min="0" max="{{ $item->outstanding() }}" name="quantities[{{ $item->id }}]"
                                   value="{{ old('quantities.'.$item->id, $item->outstanding()) }}" class="input text-right" data-receive @disabled($item->outstanding() <= 0)>
                            @error('quantities.'.$item->id)<p class="error">{{ $message }}</p>@enderror
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex gap-2">
            <button class="btn btn-success">Post goods received</button>
            <button type="button" class="btn btn-secondary" @click="document.querySelectorAll('[data-receive]').forEach(i => i.value = 0)">Clear all quantities</button>
        </div>
    </form>
</x-layouts.app>
