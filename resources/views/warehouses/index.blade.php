<x-layouts.app title="Warehouses">
    <x-page-header title="Warehouses" subtitle="Stores and branches that hold stock" />

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('warehouses.store') }}" class="card card-body space-y-3 self-start">
            @csrf
            <h2 class="font-semibold">Add warehouse</h2>
            <x-field label="Name" name="name" required />
            <x-field label="Location" name="location" />
            <input type="hidden" name="is_active" value="1">
            <button class="btn btn-primary">Add</button>
        </form>

        <div class="card lg:col-span-2 overflow-x-auto">
            <table class="table">
                <thead><tr><th>Name</th><th>Location</th><th class="num">Units in stock</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach ($warehouses as $w)
                    <tr x-data="{ editing: false }">
                        <td colspan="5" x-show="editing" x-cloak>
                            <form method="POST" action="{{ route('warehouses.update', $w) }}" class="flex flex-wrap items-end gap-2">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $w->name }}" class="input w-44" required>
                                <input name="location" value="{{ $w->location }}" class="input w-44" placeholder="Location">
                                <x-checkbox label="Active" name="is_active" :checked="$w->is_active" />
                                <button class="btn btn-primary btn-sm">Save</button>
                                <button type="button" @click="editing = false" class="btn btn-secondary btn-sm">Cancel</button>
                            </form>
                        </td>
                        <td x-show="!editing">{{ $w->name }}</td>
                        <td x-show="!editing">{{ $w->location }}</td>
                        <td x-show="!editing" class="num">{{ qty($w->total_qty) }}</td>
                        <td x-show="!editing"><x-status :status="$w->is_active ? 'active' : 'inactive'" /></td>
                        <td x-show="!editing" class="text-right"><button type="button" @click="editing = true" class="btn btn-secondary btn-sm">Edit</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
