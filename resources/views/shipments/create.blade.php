<x-layouts.app title="Upload shipment">
    <x-page-header title="Upload upcoming shipment" subtitle="The quantities show to reps as an upcoming shipment. Stock does not change until you receive the goods.">
        <a href="{{ route('shipments.index') }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    @if (session('shipment_errors'))
        <div class="card mb-4 border-red-300">
            <h2 class="border-b border-red-200 bg-red-50 px-5 py-3 font-semibold text-red-800">Problems in the file – nothing was saved</h2>
            <table class="table">
                <thead><tr><th class="w-24">Row</th><th>Problem</th></tr></thead>
                <tbody>@foreach (session('shipment_errors') as $line => $msg)<tr><td>{{ $line ?: '-' }}</td><td class="text-red-700">{{ $msg }}</td></tr>@endforeach</tbody>
            </table>
        </div>
    @endif

    <form method="POST" action="{{ route('shipments.store') }}" enctype="multipart/form-data" class="card card-body max-w-3xl space-y-4">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="Reference (container / BL / supplier invoice)" name="reference" />
            <x-select label="Supplier" name="supplier_id" :options="$suppliers" placeholder="-" />
            <x-select label="Will arrive at warehouse" name="warehouse_id" :options="$warehouses->pluck('name', 'id')" :value="$warehouses->sortBy('id')->first()?->id" required />
            <x-field label="Expected arrival date" name="expected_date" type="date" />
            <x-field label="Notes" name="notes" class="sm:col-span-2" />
        </div>
        <div>
            <label class="label">Shipment file <span class="text-red-500">*</span></label>
            <input type="file" name="file" accept=".xlsx,.csv" required class="block w-full text-sm file:mr-3 file:rounded-md file:border file:border-slate-300 file:bg-white file:px-3 file:py-1.5">
            <p class="mt-1 text-xs text-slate-500">Columns: <span class="font-mono">product_code</span>, <span class="font-mono">quantity</span>, and optionally <span class="font-mono">unit_cost</span>. Every product code must already exist.</p>
            <p class="mt-1 text-sm">Template: <a href="{{ route('shipments.template') }}" class="link">Excel</a> · <a href="{{ route('shipments.template', ['format' => 'csv']) }}" class="link">CSV</a></p>
            @error('file')<p class="error">{{ $message }}</p>@enderror
        </div>
        <button class="btn btn-primary">Upload shipment</button>
    </form>
</x-layouts.app>
