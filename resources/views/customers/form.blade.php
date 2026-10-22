<x-layouts.app :title="$customer->exists ? 'Edit customer' : 'New customer'">
    <x-page-header :title="$customer->exists ? 'Edit '.$customer->name : 'New customer'">
        <a href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    @php $isManager = auth()->user()->can('customers.credit'); @endphp
    <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" class="card card-body max-w-3xl space-y-4">
        @csrf
        @if ($customer->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field label="Customer code" name="code" :value="$customer->code" required />
            <x-field label="Name" name="name" :value="$customer->name" required class="sm:col-span-2" />
            <x-field label="Contact person" name="contact_person" :value="$customer->contact_person" />
            <x-field label="Phone" name="phone" :value="$customer->phone" />
            <x-field label="Email" name="email" type="email" :value="$customer->email" />
            <x-field label="Address" name="address" :value="$customer->address" class="sm:col-span-2" />
            <x-field label="City / area" name="city" :value="$customer->city" />
            <x-field label="Tax / VAT number" name="tax_number" :value="$customer->tax_number" />
            <x-select label="Sales rep" name="sales_rep_id" :options="$reps->pluck('name', 'id')" :value="$customer->sales_rep_id" placeholder="-" />
            <x-select label="Price level" name="price_level" :options="\App\Models\Customer::PRICE_LEVELS" :value="$customer->price_level" required />
            <x-field label="Payment terms (days)" name="payment_terms_days" type="number" min="0" max="365" :value="$customer->payment_terms_days" required />
            <x-field label="Credit limit" name="credit_limit" type="number" step="0.01" min="0" :value="$customer->credit_limit ?? 0" required
                     help="0 = no limit" :readonly="! $isManager && $customer->exists" />
        </div>
        <div class="flex flex-wrap gap-6">
            <x-checkbox label="Active" name="is_active" :checked="$customer->is_active" />
            @if ($isManager)
                <x-checkbox label="On hold (block new invoices)" name="on_hold" :checked="$customer->on_hold" />
            @endif
        </div>
        <div><button class="btn btn-primary">Save customer</button></div>
    </form>
</x-layouts.app>
