<x-layouts.guest title="Forgot password">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Forgot your password?</h1>
        <p class="mt-1.5 text-sm text-slate-500">Enter your account email and we will send you a link to choose a new one.</p>
    </div>

    @if (session('status'))
        <div class="mb-6 flex gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <x-auth-input label="Email address" name="email" type="email" icon="mail" placeholder="you@company.lk" required autofocus autocomplete="username" />
        <button class="w-full rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition hover:from-indigo-700 hover:to-indigo-600 focus:outline-none focus:ring-4 focus:ring-indigo-200">
            Email me a reset link
        </button>
    </form>

    <p class="mt-8 text-center text-sm"><a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-700">&larr; Back to sign in</a></p>
</x-layouts.guest>
