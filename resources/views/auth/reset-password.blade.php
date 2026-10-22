<x-layouts.guest title="Choose a new password">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Choose a new password</h1>
        <p class="mt-1.5 text-sm text-slate-500">Use at least 8 characters. You will be signed out on other devices.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-auth-input label="Email address" name="email" type="email" icon="mail" :value="$email" required autocomplete="username" />
        <x-auth-input label="New password" name="password" type="password" icon="lock" required autofocus autocomplete="new-password" help="At least 8 characters." />
        <x-auth-input label="Confirm new password" name="password_confirmation" type="password" icon="lock" required autocomplete="new-password" />
        <button class="w-full rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition hover:from-indigo-700 hover:to-indigo-600 focus:outline-none focus:ring-4 focus:ring-indigo-200">
            Change password
        </button>
    </form>

    <p class="mt-8 text-center text-sm"><a href="{{ route('password.request') }}" class="font-medium text-indigo-600 hover:text-indigo-700">Ask for a new link</a></p>
</x-layouts.guest>
