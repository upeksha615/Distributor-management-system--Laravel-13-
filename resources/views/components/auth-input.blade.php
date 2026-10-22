@props(['label', 'name', 'type' => 'text', 'value' => null, 'icon' => 'mail', 'help' => null])
{{-- Large input with a leading icon; password fields get a show/hide button. --}}
@php
    $icons = [
        'mail' => 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.24a2.25 2.25 0 0 1-1.07 1.92l-7.5 4.61a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.92v-.24',
        'lock' => 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
    ];
    $isPassword = $type === 'password';
@endphp
<div @if ($isPassword) x-data="{ show: false }" @endif>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-slate-700">{{ $label }}</label>
    <div class="relative">
        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$icon] }}"/>
        </svg>
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if ($isPassword) :type="show ? 'text' : 'password'" @endif
               value="{{ $isPassword ? '' : old($name, $value) }}"
               {{ $attributes->class([
                   'block w-full rounded-lg border bg-white py-3 pl-11 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-4',
                   'pr-11' => $isPassword, 'pr-3' => ! $isPassword,
                   'border-red-300 focus:border-red-500 focus:ring-red-100' => $errors->has($name),
                   'border-slate-300 focus:border-indigo-500 focus:ring-indigo-100' => ! $errors->has($name),
               ]) }}>
        @if ($isPassword)
            <button type="button" @click="show = ! show" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    :aria-label="show ? 'Hide password' : 'Show password'">
                <svg x-show="! show" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1 1 0 0 1 0-.64C3.42 7.51 7.36 4.5 12 4.5s8.57 3 9.96 7.18c.07.2.07.43 0 .64C20.58 16.49 16.64 19.5 12 19.5s-8.57-3-9.96-7.18Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                <svg x-show="show" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.48 10.48 0 0 0 1.93 12C3.23 16.34 7.24 19.5 12 19.5c.99 0 1.95-.14 2.86-.39M6.23 6.23A10.45 10.45 0 0 1 12 4.5c4.76 0 8.77 3.16 10.07 7.5a10.52 10.52 0 0 1-4.29 5.77M6.23 6.23 3 3m3.23 3.23 3.65 3.65m7.89 7.89L21 21m-3.23-3.23-3.65-3.65m0 0a3 3 0 1 0-4.24-4.24m4.24 4.24L9.88 9.88"/></svg>
            </button>
        @endif
    </div>
    @if ($help)<p class="mt-1.5 text-xs text-slate-500">{{ $help }}</p>@endif
    @error($name)<p class="mt-1.5 flex items-center gap-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
