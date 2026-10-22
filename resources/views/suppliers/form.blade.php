<x-layouts.app :title="$supplier->exists ? 'Edit supplier' : 'New supplier'">
    <x-page-header :title="$supplier->exists ? 'Edit '.$supplier->name : 'New supplier'">
        <a href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        @if ($supplier->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field label="Code" name="code" :value="$supplier->code" required />
            <x-field label="Name" name="name" :value="$supplier->name" required class="sm:col-span-2" />
            <x-field label="Contact person" name="contact_person" :value="$supplier->contact_person" />
            <x-field label="Phone" name="phone" :value="$supplier->phone" />
            <x-field label="Email" name="email" type="email" :value="$supplier->email" />
            <x-field label="Address" name="address" :value="$supplier->address" class="sm:col-span-2" />
            <x-field label="Country" name="country" :value="$supplier->country" />
            <x-field label="Payment terms (days)" name="payment_terms_days" type="number" min="0" max="365" :value="$supplier->payment_terms_days ?? 30" required
                     help="Due date for supplier bills = bill date + these days." />
        </div>
        <x-checkbox label="Active" name="is_active" :checked="$supplier->is_active" />
        <div><button class="btn btn-primary">Save supplier</button></div>
    </form>
</x-layouts.app>
