<x-layouts.app :title="$shipment->number">
    <x-page-header :title="'Shipment '.$shipment->number" :subtitle="collect([$shipment->reference, $shipment->supplier?->name, 'to '.$shipment->warehouse->name, $shipment->expected_date ? 'expected '.$shipment->expected_date->format('d/m/Y') : null])->filter()->join(' · ')">
        <span class="badge self-center {{ $shipment->isOpen() ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-700' }}">{{ \App\Models\Shipment::STATUSES[$shipment->status] }}</span>
        <a href="{{ route('shipments.index') }}" class="btn btn-secondary">All shipments</a>
    </x-page-header>

    @if ($shipment->isOpen())
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            These quantities are <strong>not in stock yet</strong>. Reps see them as an upcoming shipment. When the goods arrive, enter what you received below.
        </div>
    @endif

    @php $canReceive = $shipment->isOpen() && auth()->user()->can('shipments.manage'); $seeQty = auth()->user()->can('stock.view_qty'); @endphp
    <form method="POST" action="{{ route('shipments.receive', $shipment) }}" onsubmit="return confirm('Add the entered quantities to stock?')">
        @csrf
        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Product code</th><th>Description</th><th>Brand</th>@if ($seeQty)<th class="num">Expected</th><th class="num">Received</th>@endif<th class="num">Still to come</th>@if ($canReceive)<th class="w-36 num">Receive now</th>@endif</tr></thead>
                <tbody>
                @foreach ($shipment->items as $item)
                    <tr>
                        <td><a href="{{ route('products.show', $item->product) }}" class="link">{{ $item->product->sku }}</a><div class="font-mono text-xs text-slate-500">{{ $item->product->part_number }}</div></td>
                        <td>{{ $item->product->name }}</td>
                        <td>{{ $item->product->brand?->name }}</td>
                        @if ($seeQty)
                            <td class="num">{{ qty($item->quantity) }}</td>
                            <td class="num">{{ qty($item->received_quantity) }}</td>
                        @endif
                        <td class="num">@if ($shipment->isOpen())<x-ship-badge :qty="$item->outstanding()" />@elseif ($seeQty){{ qty($item->outstanding()) }}@else – @endif</td>
                        @if ($canReceive)
                            <td><input type="number" step="0.01" min="0" max="{{ $item->outstanding() }}" name="quantities[{{ $item->id }}]" value="{{ $item->outstanding() }}" class="input text-right" data-receive @disabled($item->outstanding() <= 0)></td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($canReceive)
            <div class="mt-4 flex flex-wrap items-end gap-2" x-data>
                <input name="note" placeholder="Note (e.g. GRN / delivery ref)" class="input w-72">
                <button class="btn btn-success">Receive into {{ $shipment->warehouse->name }} stock</button>
                <button type="button" class="btn btn-secondary" @click="document.querySelectorAll('[data-receive]').forEach(i => i.value = 0)">Clear quantities</button>
            </div>
        @endif
    </form>

    @if ($canReceive)
        <form method="POST" action="{{ route('shipments.cancel', $shipment) }}" class="card card-body mt-6 flex flex-wrap items-end gap-2" onsubmit="return confirm('Cancel what is still expected on this shipment?')">
            @csrf
            <div class="w-full text-sm font-semibold">Cancel remaining quantities</div>
            <input name="reason" required placeholder="Reason" class="input w-72">
            <button class="btn btn-danger">Cancel shipment</button>
        </form>
    @endif

    @if ($shipment->notes)<p class="mt-4 whitespace-pre-line text-sm"><span class="text-slate-500">Notes:</span> {{ $shipment->notes }}</p>@endif
    <p class="mt-2 text-xs text-slate-500">Uploaded {{ $shipment->created_at->format('d/m/Y H:i') }} by {{ $shipment->user?->name }}{{ $shipment->source_file ? ' from '.$shipment->source_file : '' }}.</p>
</x-layouts.app>
