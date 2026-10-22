<x-layouts.app :title="$user->exists ? 'Edit user' : 'New user'">
    <x-page-header :title="$user->exists ? 'Edit '.$user->name : 'New user'">
        <a href="{{ route('users.index') }}" class="btn btn-secondary">Back</a>
    </x-page-header>

    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card card-body max-w-2xl space-y-4" x-data="{ role: @js(old('role', $user->role)) }">
        @csrf
        @if ($user->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="Name" name="name" :value="$user->name" required />
            <x-field label="Email" name="email" type="email" :value="$user->email" required />
            <x-select label="Role" name="role" :options="$roles" :value="$user->role" required x-model="role" />
            <div x-show="role === 'dealer'" x-cloak>
                <x-select label="Dealer account (portal sees only this dealer)" name="customer_id" :options="$dealers" :value="$user->customer_id" placeholder="Select dealer..." />
            </div>
            <x-field label="Password" name="password" type="password" :required="! $user->exists" autocomplete="new-password"
                     :help="$user->exists ? 'Leave blank to keep the current password.' : 'At least 8 characters.'" />
            <x-field label="Confirm password" name="password_confirmation" type="password" :required="! $user->exists" autocomplete="new-password" />
        </div>
        <x-checkbox label="Active (can log in)" name="is_active" :checked="$user->is_active" />
        <div><button class="btn btn-primary">Save user</button></div>
    </form>

    @if ($user->exists)
        <form method="POST" action="{{ route('users.send-reset', $user) }}" class="card card-body mt-4 flex max-w-2xl flex-wrap items-center justify-between gap-3">
            @csrf
            <p class="text-sm text-slate-600">Email {{ $user->email }} a link to set a new password (valid for {{ config('auth.passwords.users.expire') }} minutes).</p>
            <button class="btn btn-secondary">Send password reset link</button>
        </form>
    @endif
</x-layouts.app>
