<x-layouts.app title="Customers">
    <x-page-header title="Customers" subtitle="Dealers and retailers">
        <x-export-buttons />
        <a href="{{ route('customers.create') }}" class="btn btn-primary">New customer</a>
    </x-page-header>

    <x-search-bar placeholder="Code, name or phone">
        <x-select label="Status" name="status" :options="['active' => 'Active', 'on_hold' => 'On hold', 'inactive' => 'Inactive']" :value="request('status', 'active')" class="w-36" />
    </x-search-bar>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Code</th><th>Name</th><th>City</th><th>Phone</th><th>Sales rep</th><th class="num">Credit limit</th><th class="num">Balance</th><th></th></tr></thead>
            <tbody>
            @forelse ($customers as $c)
                <tr>
                    <td><a href="{{ route('customers.show', $c) }}" class="link">{{ $c->code }}</a></td>
                    <td>{{ $c->name }}</td>
                    <td>{{ $c->city }}</td>
                    <td>{{ $c->phone }}</td>
                    <td>{{ $c->salesRep?->name }}</td>
                    <td class="num">{{ $c->credit_limit > 0 ? money($c->credit_limit, false) : 'No limit' }}</td>
                    <td class="num {{ $c->credit_limit > 0 && $c->balance > $c->credit_limit ? 'font-semibold text-red-600' : '' }}">{{ money($c->balance, false) }}</td>
                    <td>@if ($c->on_hold)<x-status status="on hold" />@endif</td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-6 text-center text-slate-500">No customers found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $customers->links() }}</div>
</x-layouts.app>
