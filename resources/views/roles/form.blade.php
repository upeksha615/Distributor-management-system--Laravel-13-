<x-layouts.app :title="$role->exists ? 'Edit role' : 'New role'">
    <x-page-header :title="$role->exists ? 'Role: '.$role->name : 'New role'">
        <a href="{{ route('roles.index') }}" class="btn btn-secondary">Back</a>
        @if ($role->exists && ! $role->is_system)
            <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Delete this role?')">@csrf @method('DELETE')<button class="btn btn-danger">Delete role</button></form>
        @endif
    </x-page-header>

    @if ($role->isSuperAdmin())
        <div class="card card-body max-w-2xl text-sm">The <strong>Super Admin</strong> role always has every permission and cannot be edited.</div>
    @else
        <form method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}" class="space-y-4">
            @csrf
            @if ($role->exists) @method('PUT') @endif
            <div class="card card-body grid max-w-3xl gap-4 sm:grid-cols-3">
                <x-field label="Name" name="name" :value="$role->name" required />
                @unless ($role->exists)
                    <x-field label="Code (letters, numbers, _)" name="slug" required help="Cannot be changed later." />
                @endunless
                <x-field label="Description" name="description" :value="$role->description" class="{{ $role->exists ? 'sm:col-span-2' : '' }}" />
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($permissions as $group => $perms)
                    <div class="card card-body" x-data>
                        <div class="mb-2 flex items-center justify-between">
                            <h2 class="font-semibold">{{ $group }}</h2>
                            <button type="button" class="link text-xs" @click="$el.closest('.card').querySelectorAll('input[type=checkbox]').forEach(c => c.checked = true)">Select all</button>
                        </div>
                        @foreach ($perms as $key => $label)
                            <label class="flex items-start gap-2 py-1 text-sm">
                                <input type="checkbox" name="permissions[]" value="{{ $key }}" class="mt-0.5 rounded border-slate-300" @checked(in_array($key, old('permissions', $granted)))>
                                <span>{{ $label }} <span class="font-mono text-[10px] text-slate-400">{{ $key }}</span></span>
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <button class="btn btn-primary">Save role</button>
        </form>
    @endif
</x-layouts.app>
