<x-layouts.app title="Roles & permissions">
    <x-page-header title="Roles & permissions" subtitle="What each role can do. Changes apply on the user's next page load and are recorded in the audit log.">
        <a href="{{ route('roles.create') }}" class="btn btn-primary">New role</a>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table text-xs">
            <thead>
            <tr>
                <th class="sticky left-0 z-10 min-w-64 bg-slate-50">Permission</th>
                @foreach ($roles as $role)
                    <th class="text-center whitespace-nowrap"><a href="{{ route('roles.edit', $role) }}" class="link">{{ $role->name }}</a>
                        <div class="font-normal normal-case text-slate-400">{{ $role->users_count }} user(s)</div></th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach ($permissions as $group => $perms)
                <tr><td colspan="{{ $roles->count() + 1 }}" class="bg-slate-100 font-semibold text-slate-700">{{ $group }}</td></tr>
                @foreach ($perms as $perm)
                    <tr>
                        <td class="sticky left-0 bg-white">{{ $perm->label }}<div class="font-mono text-[10px] text-slate-400">{{ $perm->key }}</div></td>
                        @foreach ($roles as $role)
                            <td class="text-center">
                                @if ($role->isSuperAdmin() || $role->permissions->contains('id', $perm->id))
                                    <span class="font-bold text-emerald-600">✓</span>
                                @else
                                    <span class="text-slate-300">–</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
