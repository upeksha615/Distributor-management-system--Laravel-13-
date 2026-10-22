<x-layouts.app title="Upload payment slip">
    <x-page-header title="Upload payment slip" subtitle="Record a slip received by hand, email or WhatsApp. It is evidence only – an accountant still verifies the money in the bank.">
        <a href="{{ route('payment-inbox.index') }}" class="btn btn-secondary">Inbox</a>
    </x-page-header>

    <form method="POST" action="{{ route('payment-inbox.store') }}" enctype="multipart/form-data" class="card card-body max-w-4xl space-y-4">
        @csrf
        <div class="grid gap-4 sm:grid-cols-3">
            <x-select label="Received via" name="channel" :options="['manual' => 'By hand / office', 'email' => 'Email', 'whatsapp' => 'WhatsApp']" required />
            <x-field label="Sender (email or phone)" name="sender" />
            <x-select label="Dealer (if known)" name="customer_id" :options="$dealers->mapWithKeys(fn ($d) => [$d->id => $d->name.' ('.$d->code.')'])" placeholder="Not sure yet" />
            <x-field label="Amount on slip (LKR)" name="claimed_amount" type="number" step="0.01" min="0.01" />
            <x-field label="Date on slip" name="claimed_date" type="date" />
            <x-select label="Method" name="claimed_method" :options="\App\Models\Payment::METHODS" placeholder="-" />
            <x-field label="Transaction reference / cheque no" name="claimed_reference" />
            <x-select label="Sending bank" name="bank_id" :options="$banks->pluck('name', 'id')" placeholder="-" />
            <x-select label="Paid into company account" name="company_bank_account_id" :options="$accounts->mapWithKeys(fn ($a) => [$a->id => $a->label()])" placeholder="-" />
            <x-field label="Invoice numbers the dealer mentioned" name="invoice_refs" class="sm:col-span-2" />
            <x-field label="Notes" name="notes" />
        </div>
        <div>
            <label class="label">Slip file(s) <span class="text-red-500">*</span></label>
            <input type="file" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-md file:border file:border-slate-300 file:bg-white file:px-3 file:py-1.5">
            <p class="mt-1 text-xs text-slate-500">Up to 5 files. PDF, JPG or PNG, max {{ config('distributor.max_upload_mb') }} MB each. Stored privately.</p>
            @error('files')<p class="error">{{ $message }}</p>@enderror
            @error('files.*')<p class="error">{{ $message }}</p>@enderror
        </div>
        <button class="btn btn-primary">Save to inbox</button>
    </form>
</x-layouts.app>
