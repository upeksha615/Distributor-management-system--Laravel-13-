<x-layouts.app title="New supplier bill">
    <x-page-header title="New supplier bill" subtitle="For costs without a goods received note, e.g. freight, customs duty, clearing charges or rent.">
        <a href="{{ route('supplier-bills.index') }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <form method="POST" action="{{ route('supplier-bills.store') }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="supplier_id" class="label">Supplier <span class="text-red-500">*</span></label>
                <select id="supplier_id" name="supplier_id" class="input" required>
                    <option value="">Select supplier...</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}" @selected((string) old('supplier_id', $supplierId) === (string) $s->id)>{{ $s->name }} ({{ $s->code }}) · {{ $s->payment_terms_days ?? 30 }} days</option>
                    @endforeach
                </select>
                @error('supplier_id')<p class="error">{{ $message }}</p>@enderror
            </div>
            <x-field label="Supplier invoice no" name="supplier_invoice_no" />
            <x-field label="Description" name="description" required class="sm:col-span-3" list="bill-descriptions"
                     placeholder="e.g. Freight for container MSKU1234567, Customs duty CUSDEC 2026/123, Warehouse rent October" />
            <x-field label="Bill date" name="bill_date" type="date" :value="today()->format('Y-m-d')" required />
            <x-field label="Due date" name="due_date" type="date" help="Leave blank to use the supplier's payment terms." />
            <x-field label="Amount" name="amount" type="number" step="0.01" min="0.01" required />
        </div>
        <datalist id="bill-descriptions">
            <option value="Freight charges"></option>
            <option value="Customs duty"></option>
            <option value="Clearing & forwarding charges"></option>
            <option value="Port & handling charges"></option>
            <option value="Transport"></option>
            <option value="Rent"></option>
        </datalist>
        <div><button class="btn btn-primary">Save bill</button></div>
    </form>
</x-layouts.app>
