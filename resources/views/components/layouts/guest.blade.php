@props(['title'])
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('distributor.company.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 font-sans text-slate-900 antialiased">
<div class="flex min-h-full">
    {{-- Brand panel (large screens) --}}
    <aside class="relative hidden w-1/2 overflow-hidden bg-gradient-to-br from-slate-950 via-indigo-950 to-indigo-800 text-white lg:flex lg:flex-col lg:justify-between xl:w-[55%]">
        <div class="pointer-events-none absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 28px 28px;"></div>
        <div class="pointer-events-none absolute -right-40 -top-40 h-[34rem] w-[34rem] rounded-full bg-indigo-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-48 -left-32 h-[30rem] w-[30rem] rounded-full bg-sky-500/10 blur-3xl"></div>
        <svg class="pointer-events-none absolute -bottom-24 -right-24 h-[28rem] w-[28rem] text-white/[0.05] animate-[spin_90s_linear_infinite]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.61-.22l-2.49 1a7.3 7.3 0 0 0-1.69-.98l-.38-2.65A.49.49 0 0 0 14 2h-4a.49.49 0 0 0-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1a.5.5 0 0 0-.61.22l-2 3.46a.49.49 0 0 0 .12.64l2.11 1.65c-.04.32-.07.65-.07.98s.03.66.07.98l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46a.5.5 0 0 0-.12-.64l-2.11-1.65ZM12 15.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7Z"/>
        </svg>

        <div class="relative px-12 pt-12 xl:px-16">
            <div class="flex items-center gap-3">
                <x-brand-mark class="h-11 w-11" />
                <span class="text-lg font-semibold tracking-tight">{{ config('distributor.company.name') }}</span>
            </div>
        </div>

        <div class="relative max-w-2xl px-12 xl:px-16">
            <p class="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-indigo-100 ring-1 ring-white/15">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Spare parts distribution system
            </p>
            <h2 class="text-3xl font-semibold leading-tight tracking-tight xl:text-4xl 2xl:text-5xl"><span class="whitespace-nowrap">Every part, every dealer,</span><br><span class="bg-gradient-to-r from-indigo-200 to-sky-300 bg-clip-text text-transparent">every rupee accounted for.</span></h2>
            <ul class="mt-10 space-y-5 text-sm text-indigo-100/90">
                <li class="flex gap-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/15">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m20 7-8-4-8 4m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </span>
                    <span><strong class="block text-white">Live stock &amp; shipments</strong>Availability by category, brand and part number, with upcoming containers.</span>
                </li>
                <li class="flex gap-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/15">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.04A11.96 11.96 0 0 1 3.6 6 12 12 0 0 0 3 9.75c0 5.6 3.82 10.3 9 11.62 5.18-1.33 9-6.03 9-11.62 0-1.31-.21-2.57-.6-3.75h-.15c-3.2 0-6.1-1.25-8.25-3.29Z"/></svg>
                    </span>
                    <span><strong class="block text-white">Verified payments &amp; cheques</strong>Slips checked against the bank, cheques tracked until they clear.</span>
                </li>
                <li class="flex gap-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/15">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.14-7.5 11.25-7.5 11.25S4.5 17.64 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                    </span>
                    <span><strong class="block text-white">Reps in the field</strong>Dealer check-ins, orders and collections from any phone.</span>
                </li>
            </ul>
        </div>

        <p class="relative px-12 pb-10 text-xs text-indigo-200/70 xl:px-16">&copy; {{ date('Y') }} {{ config('distributor.company.name') }}</p>
    </aside>

    {{-- Form panel --}}
    <main class="relative flex flex-1 items-center justify-center px-4 pb-20 pt-10 sm:px-8">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-64 bg-gradient-to-b from-indigo-100/70 to-transparent lg:hidden"></div>
        <div class="relative w-full max-w-sm">
            <div class="mb-8 flex flex-col items-center text-center lg:hidden">
                <x-brand-mark class="h-14 w-14" />
                <p class="mt-3 text-base font-semibold text-slate-900">{{ config('distributor.company.name') }}</p>
            </div>

            {{ $slot }}
        </div>
        <x-dt-credit class="absolute inset-x-0 bottom-5 px-4" />
    </main>
</div>
</body>
</html>
