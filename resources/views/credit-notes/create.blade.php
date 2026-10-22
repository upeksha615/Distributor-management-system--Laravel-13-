<x-layouts.app :title="'Credit note for '.$invoice->number">
    <x-page-header :title="'Return / credit: '.$invoice->number" :subtitle="$invoice->customer->name">
        <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <form method="POST" action="{{ route('credit-notes.store', $invoice) }}" class="space-y-4">
        @csrf
        <div class="card card-body grid gap-4 sm:grid-cols-3">
            <x-field label="Credit date" name="credit_date" type="date" :value="today()->format('Y-m-d')" required />
            <x-field label="Reason" name="reason" required placeholder="e.g. Damaged, wrong part, price correction" class="sm:col-span-2" />
            <x-checkbox label="Put returned goods back into stock" name="restock" :checked="true" class="sm:col-span-3" />
        </div>

        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>SKU</th><th>Description</th><th class="num">Invoiced</th><th class="num">Already returned</th><th class="num">Net price</th><th class="w-36 num">Return qty</th></tr></thead>
                <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->product->sku }}</td>
                        <td>{{ $item->product->name }}</td>
                        <td class="num">{{ qty($item->quantity) }}</td>
                        <td class="num">{{ qty($item->returned_quantity) }}</td>
                        <td class="num">{{ money($item->netUnitPrice(), false) }}</td>
                        <td>
                            <input type="number" step="0.01" min="0" max="{{ $item->returnableQuantity() }}" name="quantities[{{ $item->id }}]"
                                   value="{{ old('quantities.'.$item->id, 0) }}" class="input text-right" @disabled($item->returnableQuantity() <= 0)>
                            @error('quantities.'.$item->id)<p class="error">{{ $message }}</p>@enderror
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-sm text-slate-500">Invoice-level discount and tax are applied to the credit in proportion. If the invoice is already paid, the credit becomes unallocated customer credit you can apply to other invoices.</p>

        <button class="btn btn-primary">Create credit note</button>
    </form>
</x-layouts.app>
