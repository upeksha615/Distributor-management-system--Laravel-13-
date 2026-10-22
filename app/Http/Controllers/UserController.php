<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', [
            'users' => User::with(['roleModel', 'customer'])->orderBy('name')->get(),
            'roles' => Role::withCount('permissions')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => 'sales_rep', 'is_active' => true])] + $this->formData());
    }

    public function store(Request $request)
    {
        $user = User::create($this->validated($request));
        AuditLog::record('user.create', $user, "Created user {$user->email} ({$user->roleLabel()}).");

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        return view('users.form', ['user' => $user] + $this->formData());
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && ($data['role'] !== $user->role || ! $data['is_active'])) {
            return back()->with('error', 'You cannot change your own role or deactivate yourself.');
        }

        $user->fill($data);
        if ($user->isDirty(['role', 'is_active', 'password', 'customer_id'])) {
            AuditLog::record('user.update', $user, "Updated {$user->email}: ".collect(['role', 'is_active', 'password'])->filter(fn ($f) => $user->isDirty($f))
                ->map(fn ($f) => $f === 'password' ? 'password changed' : "{$f} ".var_export($user->getOriginal($f), true).' → '.var_export($user->$f, true))->join(', '));
        }
        $user->save();

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    private function formData(): array
    {
        return [
            'roles' => Role::orderBy('name')->pluck('name', 'slug'),
            'dealers' => Customer::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->name.' ('.$c->code.')']),
        ];
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::exists('roles', 'slug')],
            'customer_id' => ['nullable', 'required_if:role,dealer', 'exists:customers,id'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ]) + ['is_active' => $request->boolean('is_active')];

        if ($data['role'] !== 'dealer') {
            $data['customer_id'] = null;
        }
        // Only a Super Admin may create or promote another Super Admin.
        if ($data['role'] === 'super_admin' && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Only a Super Admin can assign the Super Admin role.');
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
