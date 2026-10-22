<x-layouts.app title="Suppliers">
    <x-page-header title="Suppliers">
        <x-export-buttons />
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary">New supplier</a>
    </x-page-header>

    <x-search-bar placeholder="Code or name" />

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Code</th><th>Name</th><th>Contact</th><th>Phone</th><th>Country</th><th class="num">POs</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($suppliers as $s)
                <tr>
                    <td><a href="{{ route('suppliers.show', $s) }}" class="link">{{ $s->code }}</a></td>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->contact_person }}</td>
                    <td>{{ $s->phone }}</td>
                    <td>{{ $s->country }}</td>
                    <td class="num">{{ $s->purchase_orders_count }}</td>
                    <td><x-status :status="$s->is_active ? 'active' : 'inactive'" /></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-500">No suppliers yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $suppliers->links() }}</div>
</x-layouts.app>
