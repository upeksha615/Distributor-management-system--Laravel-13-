<x-layouts.app title="Company bank accounts">
    <x-page-header title="Company bank accounts" subtitle="Accounts dealers pay into and that suppliers are paid from">
        <a href="{{ route('bank-accounts.create') }}" class="btn btn-primary">Add account</a>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Account name</th><th>Bank / branch</th><th>Number</th><th>Type</th><th>Currency</th><th>Dealer payments</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($accounts as $a)
                <tr>
                    <td class="font-medium">{{ $a->account_name }}</td>
                    <td>{{ $a->bank->name }}<div class="text-xs text-slate-500">{{ $a->branch?->name }}</div></td>
                    <td class="font-mono">{{ $a->maskedNumber() }}</td>
                    <td>{{ ucfirst($a->account_type) }}</td>
                    <td>{{ $a->currency }}</td>
                    <td>{{ $a->receives_dealer_payments ? 'Yes' : 'No' }}</td>
                    <td><x-status :status="$a->is_active ? 'active' : 'inactive'" /></td>
                    <td class="text-right"><a href="{{ route('bank-accounts.edit', $a) }}" class="btn btn-secondary btn-sm">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-6 text-center text-slate-500">No company bank accounts yet. Add the accounts dealers transfer money into.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @cannot('bank_accounts.view_numbers')<p class="mt-2 text-xs text-slate-500">Account numbers are partly hidden for your role.</p>@endcannot
</x-layouts.app>
