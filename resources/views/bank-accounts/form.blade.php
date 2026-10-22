<x-layouts.app :title="$account->exists ? 'Edit bank account' : 'New bank account'">
    <x-page-header :title="$account->exists ? 'Edit '.$account->account_name : 'New company bank account'">
        <a href="{{ route('bank-accounts.index') }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <form method="POST" action="{{ $account->exists ? route('bank-accounts.update', $account) : route('bank-accounts.store') }}" class="card card-body max-w-3xl space-y-4"
          x-data="{ bank: @js(old('bank_id', $account->bank_id)), branches: [], branch: @js(old('bank_branch_id', $account->bank_branch_id)),
                    async load() { this.branches = this.bank ? await (await fetch('{{ url('banks') }}/' + this.bank + '/branch-lookup', { headers: { Accept: 'application/json' } })).json() : []; } }"
          x-init="load()">
        @csrf
        @if ($account->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="Account name" name="account_name" :value="$account->account_name" required help="e.g. Main collection account" class="sm:col-span-2" />
            <div>
                <label class="label">Bank <span class="text-red-500">*</span></label>
                <select name="bank_id" x-model="bank" @change="branch = ''; load()" class="input" required>
                    <option value="">Select bank...</option>
                    @foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
                @error('bank_id')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Branch</label>
                <select name="bank_branch_id" x-model="branch" class="input">
                    <option value="">-</option>
                    <template x-for="b in branches" :key="b.id"><option :value="b.id" x-text="b.name + (b.code ? ' (' + b.code + ')' : '')" :selected="b.id == branch"></option></template>
                </select>
                <p class="mt-1 text-xs text-slate-500">Branches are maintained under Banks & branches.</p>
            </div>
            <x-field label="Account number" name="account_number" :value="$account->account_number" required />
            <x-select label="Account type" name="account_type" :options="['current' => 'Current', 'savings' => 'Savings']" :value="$account->account_type" />
            <x-field label="Currency" name="currency" :value="$account->currency" required />
            <x-field label="Opening balance" name="opening_balance" type="number" step="0.01" :value="$account->opening_balance" help="For bank reconciliation." />
            <x-field label="Opening balance date" name="opening_date" type="date" :value="$account->opening_date?->format('Y-m-d')" />
            <x-field label="Notes" name="notes" :value="$account->notes" />
        </div>
        <div class="flex flex-wrap gap-6">
            <x-checkbox label="Dealers may pay into this account" name="receives_dealer_payments" :checked="$account->receives_dealer_payments" />
            <x-checkbox label="Active" name="is_active" :checked="$account->is_active" />
        </div>
        <button class="btn btn-primary">Save account</button>
    </form>
</x-layouts.app>
