<x-layouts.app title="Users">
    <x-page-header title="Users" subtitle="Staff accounts and dealer portal logins">
        @can('roles.manage')<a href="{{ route('roles.index') }}" class="btn btn-secondary">Roles & permissions</a>@endcan
        <a href="{{ route('users.create') }}" class="btn btn-primary">New user</a>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Dealer</th><th>Last login</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr>
                    <td>{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td>{{ $u->roleLabel() }}</td>
                    <td>{{ $u->customer?->name }}</td>
                    <td class="text-slate-500">{{ $u->last_login_at?->format('d/m/Y H:i') ?? 'Never' }}</td>
                    <td><x-status :status="$u->is_active ? 'active' : 'inactive'" /></td>
                    <td class="text-right"><a href="{{ route('users.edit', $u) }}" class="btn btn-secondary btn-sm">Edit</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="card mt-6 overflow-x-auto">
        <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">Roles</h2>
        <table class="table">
            <thead><tr><th>Role</th><th>What it is for</th><th class="num">Permissions</th></tr></thead>
            <tbody>
            @foreach ($roles as $role)
                <tr>
                    <td class="font-medium">@can('roles.manage')<a href="{{ route('roles.edit', $role) }}" class="link">{{ $role->name }}</a>@else{{ $role->name }}@endcan</td>
                    <td class="text-slate-600">{{ $role->description }}</td>
                    <td class="num">{{ $role->isSuperAdmin() ? 'All' : $role->permissions_count }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
