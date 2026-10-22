<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Super Admin: roles and their permissions (the permission matrix). */
class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->withCount('users')->orderBy('name')->get();
        $permissions = Permission::orderBy('id')->get()->groupBy('group');

        return view('roles.index', compact('roles', 'permissions'));
    }

    public function create()
    {
        return view('roles.form', ['role' => new Role(), 'permissions' => $this->catalog(), 'granted' => []]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $role = DB::transaction(function () use ($data) {
            $role = Role::create(['slug' => $data['slug'], 'name' => $data['name'], 'description' => $data['description'] ?? null]);
            $role->permissions()->sync($this->permissionIds($data['permissions'] ?? []));

            return $role;
        });
        AuditLog::record('role.create', $role, "Created role {$role->name} with ".count($data['permissions'] ?? []).' permission(s).');

        return redirect()->route('roles.index')->with('success', "Role {$role->name} created.");
    }

    public function edit(Role $role)
    {
        return view('roles.form', [
            'role' => $role,
            'permissions' => $this->catalog(),
            'granted' => $role->permissions()->pluck('key')->all(),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        abort_if($role->isSuperAdmin(), 403, 'The Super Admin role always has every permission.');
        $data = $this->validated($request, $role);

        DB::transaction(function () use ($role, $data) {
            $before = $role->permissions()->pluck('key');
            $role->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
            $role->permissions()->sync($this->permissionIds($data['permissions'] ?? []));

            $after = collect($data['permissions'] ?? []);
            $added = $after->diff($before)->values();
            $removed = $before->diff($after)->values();
            if ($added->isNotEmpty() || $removed->isNotEmpty()) {
                AuditLog::record('role.permissions', $role, "Role {$role->name}: "
                    .($added->isNotEmpty() ? 'granted '.$added->join(', ') : '')
                    .($added->isNotEmpty() && $removed->isNotEmpty() ? '; ' : '')
                    .($removed->isNotEmpty() ? 'removed '.$removed->join(', ') : ''));
            }
        });

        return redirect()->route('roles.index')->with('success', "Role {$role->name} updated.");
    }

    public function destroy(Role $role)
    {
        abort_if($role->is_system, 403, 'Built-in roles cannot be deleted. Remove their permissions instead.');
        if ($role->users()->exists()) {
            return back()->with('error', 'Move the users in this role to another role first.');
        }
        AuditLog::record('role.delete', null, "Deleted role {$role->name}.");
        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Role deleted.');
    }

    private function catalog(): array
    {
        return Permissions::CATALOG;
    }

    private function permissionIds(array $keys): array
    {
        return Permission::whereIn('key', $keys)->pluck('id')->all();
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'max:100'],
            'slug' => [$role ? 'prohibited' : 'required', 'alpha_dash', 'max:50', Rule::unique('roles', 'slug')],
            'description' => ['nullable', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(array_keys(Permissions::all()))],
        ]);
    }
}
