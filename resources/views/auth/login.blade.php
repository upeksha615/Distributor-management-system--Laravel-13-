<x-layouts.guest title="Log in">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Welcome back</h1>
        <p class="mt-1.5 text-sm text-slate-500">Sign in to your account to continue.</p>
    </div>

    @if (session('status'))
        <div class="mb-6 flex gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ busy: false }" @submit="busy = true">
        @csrf
        <x-auth-input label="Email address" name="email" type="email" icon="mail" placeholder="you@company.lk" required autofocus autocomplete="username" />
        <x-auth-input label="Password" name="password" type="password" icon="lock" placeholder="••••••••" required autocomplete="current-password" />

        <div class="flex items-center justify-between text-sm">
            <label class="flex cursor-pointer items-center gap-2 text-slate-600">
                <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"> Remember me
            </label>
            <a href="{{ route('password.request') }}" class="font-medium text-indigo-600 hover:text-indigo-700">Forgot your password?</a>
        </div>

        <button class="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition hover:from-indigo-700 hover:to-indigo-600 focus:outline-none focus:ring-4 focus:ring-indigo-200 disabled:opacity-70"
                :disabled="busy">
            <svg x-show="busy" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/></svg>
            <span x-text="busy ? 'Signing in…' : 'Sign in'">Sign in</span>
        </button>
    </form>

    <p class="mt-10 flex items-center justify-center gap-1.5 text-xs text-slate-400">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
        Authorised staff and dealers only. Activity is logged.
    </p>
</x-layouts.guest>
