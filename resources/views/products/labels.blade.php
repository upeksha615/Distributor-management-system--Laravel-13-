<x-layouts.app title="Print labels">
    <x-page-header title="Print barcode labels" subtitle="Add products and the number of labels for each. Products without a barcode use their SKU." />

    <form method="POST" action="{{ route('labels.print') }}" target="_blank" class="space-y-4" x-data>
        @csrf
        <div class="card card-body flex flex-wrap items-end gap-6">
            <div>
                <label class="label">Layout</label>
                <select name="layout" class="input w-64">
                    <option value="sheet">A4 sticker sheet, 3 × 8 (70 × 37 mm)</option>
                    <option value="roll">Label printer roll (50 × 25 mm)</option>
                </select>
            </div>
            <x-checkbox label="Show retail price" name="show_price" :checked="true" class="pb-2" />
        </div>

        @include('partials.line-items', ['mode' => 'stock', 'quantityHeader' => 'Labels', 'items' => []])

        <button class="btn btn-primary">Print labels</button>
    </form>
</x-layouts.app>
