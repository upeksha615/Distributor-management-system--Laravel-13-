<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} · {{ config('distributor.company.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-700 antialiased">
@php
    $user = auth()->user();
    // [label, route (or [route, param]), permission, active pattern]
    $nav = [
        ['Dashboard', 'dashboard', 'dashboard.view', 'dashboard'],
        'Payments desk',
        ['Payment slip inbox', 'payment-inbox.index', 'payments.record', 'payment-inbox.*'],
        ['Verify payments', 'payment-verification.index', 'payments.verify', 'payment-verification.*'],
        ['Dealer payments', 'payments.index', 'payments.view', 'payments.*'],
        ['Cheque register', 'cheques.index', 'payments.view', 'cheques.*'],
        'Field sales',
        ['Rep dashboard', 'rep.index', 'field.rep', 'rep.*'],
        ['Field activity', 'field.index', 'field.monitor', 'field.*'],
        'Sales',
        ['Dealers', 'customers.index', 'customers.view', 'customers.*'],
        ['Quotations', 'quotations.index', 'quotations.manage', 'quotations.*'],
        ['Sales orders', 'sales-orders.index', 'sales_orders.manage', 'sales-orders.*'],
        ['Invoices', 'invoices.index', 'invoices.view', 'invoices.*'],
        ['Credit notes', 'credit-notes.index', 'credit_notes.view', 'credit-notes.*'],
        ['Deliveries', 'deliveries.index', 'deliveries.manage', 'deliveries.*'],
        'Inventory',
        ['Products & stock check', 'products.index', 'products.view', 'products.*'],
        ['Stock levels', 'stock.index', 'stock.view_qty', 'stock.index'],
        ['Upcoming shipments', 'shipments.index', 'shipments.view', 'shipments.*'],
        ['Stock movements', 'stock.movements', 'stock.view_qty', 'stock.movements'],
        ['Adjust stock', 'stock.adjust', 'stock.adjust', 'stock.adjust'],
        ['Transfer stock', 'stock.transfer', 'stock.transfer', 'stock.transfer'],
        ['Barcode labels', 'labels.index', 'products.manage', 'labels.*'],
        'Purchasing',
        ['Suppliers', 'suppliers.index', 'suppliers.view', 'suppliers.*'],
        ['Purchase orders', 'purchase-orders.index', 'purchases.manage', 'purchase-orders.*'],
        ['Goods received', 'goods-receipts.index', 'purchases.receive', 'goods-receipts.*'],
        'Accounts',
        ['Supplier bills', 'supplier-bills.index', 'payables.view', 'supplier-bills.*'],
        ['Supplier payments', 'supplier-payments.index', 'payables.view', 'supplier-payments.*'],
        ['Expenses', 'expenses.index', 'expenses.manage', 'expenses.*'],
        ['Bank reconciliation', 'reconciliation.index', 'reconciliation.manage', 'reconciliation.*'],
        'Reports',
        ['Daily summary', 'reports.daily', 'reports.finance', 'reports.daily'],
        ['Profit & loss', 'reports.profit-loss', 'reports.finance', 'reports.profit-loss'],
        ['Sales & margin', 'reports.sales', 'reports.finance', 'reports.sales'],
        ['Aged debtors', 'reports.aged-debtors', 'invoices.view', 'reports.aged-debtors'],
        ['Aged creditors', 'reports.aged-creditors', 'payables.view', 'reports.aged-creditors'],
        ['Expense report', 'reports.expenses', 'reports.finance', 'reports.expenses'],
        ['Stock valuation', 'reports.stock-valuation', 'reports.stock', 'reports.stock-valuation'],
        ['Low stock / reorder', 'reports.low-stock', 'reports.stock', 'reports.low-stock'],
        'Setup',
        ['Categories', ['lookups.index', 'categories'], 'products.manage', null],
        ['Brands', ['lookups.index', 'brands'], 'products.manage', null],
        ['Warehouses', 'warehouses.index', 'warehouses.manage', 'warehouses.*'],
        ['Banks & branches', 'banks.index', 'banks.manage', 'banks.*'],
        ['Company bank accounts', 'bank-accounts.index', 'banks.manage', 'bank-accounts.*'],
        ['Expense categories', 'expense-categories.index', 'expenses.manage', 'expense-categories.*'],
        ['Import data', 'imports.index', 'imports.manage', 'imports.*'],
        ['Users', 'users.index', 'users.manage', 'users.*'],
        ['Roles & permissions', 'roles.index', 'roles.manage', 'roles.*'],
        ['Settings', 'settings.edit', 'settings.manage', 'settings.*'],
        ['Notice board', 'notices.index', 'notices.manage', 'notices.*'],
        ['Holidays', 'holidays.index', 'notices.manage', 'holidays.*'],
        ['Audit log', 'audit.index', 'audit.view', 'audit.*'],
        ['Scheduled tasks', 'system.index', 'system.manage', 'system.index'],
        ['Email settings', 'system.mail', 'system.manage', 'system.mail*'],
        ['Email log', 'system.emails', 'system.manage', 'system.emails'],
    ];
    // Skip links whose route is not registered yet or the user may not open, then group them under their headings.
    $groups = [];
    $heading = '';
    foreach ($nav as $item) {
        if (is_string($item)) { $heading = $item; continue; }
        $route = is_array($item[1]) ? $item[1][0] : $item[1];
        if (! \Illuminate\Support\Facades\Route::has($route) || ! $user->can($item[2])) { continue; }
        $href = is_array($item[1]) ? route($item[1][0], $item[1][1]) : route($item[1]);
        $active = $item[3] ? request()->routeIs($item[3]) : request()->url() === $href;
        $groups[$heading][] = ['label' => $item[0], 'href' => $href, 'active' => $active, 'route' => $route];
    }
    $activeGroup = collect($groups)->search(fn ($links) => collect($links)->contains('active', true));

    // Work waiting for this user, shown as counters in the menu.
    $badges = [];
    if ($user->can('payments.verify')) {
        $badges['payment-verification.index'] = \App\Models\Payment::awaitingVerification()->count();
    }
    if ($user->can('payments.record') && \Illuminate\Support\Facades\Route::has('payment-inbox.index')) {
        $badges['payment-inbox.index'] = \App\Models\PaymentSubmission::whereIn('status', ['new', 'matched', 'info_requested'])->count();
    }

    // Heroicons (outline) per menu section.
    $icons = [
        '' => 'm2.25 12 8.95-8.95a1.13 1.13 0 0 1 1.6 0L21.75 12M4.5 9.75v10.13c0 .62.5 1.12 1.13 1.12H9.75v-4.88c0-.62.5-1.12 1.13-1.12h2.25c.62 0 1.12.5 1.12 1.12V21h4.13c.62 0 1.12-.5 1.12-1.12V9.75M8.25 21h8.25',
        'Payments desk' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z',
        'Field sales' => 'M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z M19.5 10.5c0 7.14-7.5 11.25-7.5 11.25S4.5 17.64 4.5 10.5a7.5 7.5 0 1 1 15 0Z',
        'Sales' => 'M2.25 3h1.39c.51 0 .95.34 1.09.84l.38 1.44m0 0L6.75 12h10.88c.42-1.03 2.32-5.87 2.98-7.74A.75.75 0 0 0 19.9 3.25H5.11Zm1.64 8.75a3 3 0 0 0-3 3h15.75m-12.75-3h11.22M7.5 19.5a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm11.25 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z',
        'Inventory' => 'm20.25 7.5-.63 10.63a2.25 2.25 0 0 1-2.24 2.12H6.62a2.25 2.25 0 0 1-2.24-2.12L3.75 7.5M10 11.25h4M3.38 7.5h17.25c.62 0 1.12-.5 1.12-1.13v-1.5c0-.62-.5-1.12-1.12-1.12H3.38c-.63 0-1.13.5-1.13 1.12v1.5c0 .63.5 1.13 1.13 1.13Z',
        'Purchasing' => 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.38A1.13 1.13 0 0 1 2.25 17.63V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.13c.62 0 1.13-.5 1.12-1.13a17.9 17.9 0 0 0-3.21-9.64 2.06 2.06 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.18V6.11c0-.56-.42-1.05-.98-1.11a48.4 48.4 0 0 0-10.04 0c-.56.06-.98.55-.98 1.11v8.14m12 0h-12',
        'Accounts' => 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.33A48.4 48.4 0 0 0 12 9.75c-2.55 0-5.06.2-7.5.58V21M3 21h18M12 6.75h.01v.01H12V6.75Z',
        'Reports' => 'M3 13.13C3 12.5 3.5 12 4.13 12h2.25c.62 0 1.12.5 1.12 1.13v6.75C7.5 20.5 7 21 6.38 21H4.13A1.13 1.13 0 0 1 3 19.88v-6.75ZM9.75 8.63c0-.63.5-1.13 1.13-1.13h2.25c.62 0 1.12.5 1.12 1.13v11.25c0 .62-.5 1.12-1.12 1.12h-2.25a1.13 1.13 0 0 1-1.13-1.12V8.63ZM16.5 4.13c0-.63.5-1.13 1.13-1.13h2.25C20.5 3 21 3.5 21 4.13v15.75c0 .62-.5 1.12-1.12 1.12h-2.25a1.13 1.13 0 0 1-1.13-1.12V4.13Z',
        'Setup' => 'M10.34 3.94c.09-.54.56-.94 1.11-.94h1.1c.55 0 1.02.4 1.11.94l.15.89c.07.42.38.76.77.93.39.16.84.14 1.19-.11l.73-.52c.45-.32 1.06-.27 1.45.12l.78.78c.39.39.44 1 .12 1.45l-.52.73c-.25.35-.27.8-.11 1.19.17.39.51.7.93.77l.89.15c.54.09.94.56.94 1.11v1.1c0 .55-.4 1.02-.94 1.11l-.89.15c-.42.07-.76.38-.93.77-.16.39-.14.84.11 1.19l.52.73c.32.45.27 1.06-.12 1.45l-.78.78c-.39.39-1 .44-1.45.12l-.73-.52c-.35-.25-.8-.27-1.19-.11-.39.17-.7.51-.77.93l-.15.89c-.09.54-.56.94-1.11.94h-1.1c-.55 0-1.02-.4-1.11-.94l-.15-.89a1.27 1.27 0 0 0-.77-.93 1.24 1.24 0 0 0-1.19.11l-.73.52c-.45.32-1.06.27-1.45-.12l-.78-.78a1.13 1.13 0 0 1-.12-1.45l.52-.73c.25-.35.27-.8.11-1.19a1.27 1.27 0 0 0-.93-.77l-.89-.15A1.13 1.13 0 0 1 3 12.55v-1.1c0-.55.4-1.02.94-1.11l.89-.15c.42-.07.76-.38.93-.77.16-.39.14-.84-.11-1.19l-.52-.73a1.13 1.13 0 0 1 .12-1.45l.78-.78c.39-.39 1-.44 1.45-.12l.73.52c.35.25.8.27 1.19.11.39-.17.7-.51.77-.93l.15-.89ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    ];
    $initials = collect(explode(' ', trim($user->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp

<div x-data="{ menu: false, q: '' }" class="min-h-screen lg:flex">
    {{-- Sidebar --}}
    <aside :class="menu ? 'translate-x-0' : '-translate-x-full'"
           class="no-print fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white text-slate-800 shadow-xl transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:shadow-none">
        <div class="flex items-center gap-3 px-5 pb-4 pt-5">
            <x-brand-mark class="h-9 w-9 shrink-0" />
            <div class="min-w-0">
                <div class="truncate text-sm font-semibold text-slate-900">{{ config('distributor.company.name') }}</div>
                <div class="text-[11px] text-slate-500">Distribution system</div>
            </div>
            <button @click="menu = false" class="ml-auto rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 lg:hidden" aria-label="Close menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="px-4 pb-3">
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.2-5.2m0 0A7.5 7.5 0 1 0 5.2 5.2a7.5 7.5 0 0 0 10.6 10.6Z"/></svg>
                <input x-model="q" type="search" placeholder="Find a page…" aria-label="Find a page"
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-indigo-100">
            </div>
        </div>

        <nav class="flex-1 space-y-2 overflow-y-auto px-3 pb-6 text-sm [scrollbar-width:thin]">
            @foreach ($groups as $heading => $links)
                @php $labels = collect($links)->pluck('label')->map(fn ($l) => mb_strtolower($l))->values(); @endphp
                <div x-data="{ open: {{ $heading === '' || $heading === $activeGroup ? 'true' : 'false' }}, labels: @js($labels) }"
                     x-init="if (@js($heading) !== '') { try { const v = localStorage.getItem('nav:' + @js($heading)); if (v !== null && @js($heading !== $activeGroup)) open = v === '1'; } catch (e) {} }"
                     x-show="! q || labels.some(l => l.includes(q.toLowerCase()))"
                     @class(['border-t border-slate-200 pt-2' => ! $loop->first])>
                    @if ($heading !== '')
                        <button type="button" @click="open = ! open; try { localStorage.setItem('nav:' + @js($heading), open ? '1' : '0') } catch (e) {}"
                                class="group flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[11px] font-semibold uppercase tracking-wider {{ $heading === $activeGroup ? 'text-indigo-700' : 'text-slate-700 hover:text-slate-900' }}">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$heading] ?? $icons['Setup'] }}"/></svg>
                            <span class="flex-1">{{ $heading }}</span>
                            <svg class="h-3.5 w-3.5 transition-transform" :class="(open || q) && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </button>
                    @endif
                    <div x-show="open || q" class="{{ $heading !== '' ? 'ml-4 border-l border-slate-200 pl-2' : '' }} space-y-0.5">
                        @foreach ($links as $link)
                            <a href="{{ $link['href'] }}" x-show="! q || @js(mb_strtolower($link['label'])).includes(q.toLowerCase())"
                               @class([
                                   'group relative flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 transition',
                                   'bg-indigo-50 font-semibold text-indigo-800 ring-1 ring-indigo-100' => $link['active'],
                                   'text-slate-800 hover:bg-slate-100 hover:text-slate-950' => ! $link['active'],
                               ])>
                                @if ($heading === '')
                                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[''] }}"/></svg>
                                @endif
                                <span class="flex-1 truncate">{{ $link['label'] }}</span>
                                @if (! empty($badges[$link['route']]))
                                    <span class="rounded-full px-1.5 py-px text-[10px] font-semibold {{ $link['active'] ? 'bg-indigo-600 text-white' : 'bg-amber-100 text-amber-800' }}">{{ $badges[$link['route']] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>
    </aside>
    <div x-show="menu" x-cloak @click="menu = false" x-transition.opacity class="fixed inset-0 z-30 bg-slate-900/20 backdrop-blur-sm lg:hidden"></div>

    <div class="flex min-h-screen min-w-0 flex-1 flex-col">
        @php
            // Bell: work waiting for this user (counts only).
            $alerts = collect([
                ['Payments to verify', $badges['payment-verification.index'] ?? 0, \Illuminate\Support\Facades\Route::has('payment-verification.index') ? route('payment-verification.index') : null],
                ['Payment slips in the inbox', $badges['payment-inbox.index'] ?? 0, \Illuminate\Support\Facades\Route::has('payment-inbox.index') ? route('payment-inbox.index') : null],
            ])->filter(fn ($a) => $a[1] > 0)->values();
            $alertTotal = $alerts->sum(fn ($a) => $a[1]);
        @endphp
        <header class="no-print sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur">
            <div class="h-0.5 bg-gradient-to-r from-indigo-500 via-sky-400 to-emerald-400"></div>
            <div class="flex items-center gap-3 px-4 py-2.5 lg:px-6">
                <button @click="menu = true" class="rounded-lg p-2 text-slate-700 hover:bg-slate-100 lg:hidden" aria-label="Open menu">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                </button>

                {{-- Where am I --}}
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 text-xs text-slate-500">
                        <a href="{{ route('dashboard') }}" class="hover:text-indigo-600" aria-label="Dashboard">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.95-8.95a1.13 1.13 0 0 1 1.6 0L21.75 12M4.5 9.75v10.13c0 .62.5 1.12 1.13 1.12H9.75v-4.88c0-.62.5-1.12 1.13-1.12h2.25c.62 0 1.12.5 1.12 1.12V21h4.13c.62 0 1.12-.5 1.12-1.12V9.75"/></svg>
                        </a>
                        @if ($activeGroup)
                            <svg class="h-3 w-3 text-slate-300" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                            <span class="truncate">{{ $activeGroup }}</span>
                        @endif
                    </div>
                    <div class="truncate text-base font-semibold text-slate-900">{{ $title ?? 'Dashboard' }}</div>
                </div>

                {{-- Parts search --}}
                @if ($user->can('products.view') && \Illuminate\Support\Facades\Route::has('products.index'))
                    <x-part-search class="ml-6 hidden max-w-md flex-1 md:block" />
                @endif

                <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                    {{-- Sri Lanka time --}}
                    <div x-data="{ t: '' }" x-init="const f = () => t = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: @js(config('app.timezone')) }); f(); setInterval(f, 15000)"
                         class="hidden items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-600 lg:flex" title="{{ config('app.timezone') }}">
                        <svg class="h-4 w-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        <span>{{ now()->format('D, d M') }}</span>
                        <span class="font-semibold tabular-nums text-slate-800" x-text="t">{{ now()->format('H:i') }}</span>
                    </div>

                    {{-- Bell --}}
                    <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                        <button @click="open = ! open" @click.outside="open = false" class="relative rounded-full p-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900" aria-label="Notifications">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.86 17.08a23.85 23.85 0 0 0 5.45-1.31A8.97 8.97 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.97 8.97 0 0 1-2.31 6.02c1.73.64 3.56 1.09 5.45 1.31m5.72 0a24.26 24.26 0 0 1-5.72 0m5.72 0a3 3 0 1 1-5.72 0"/></svg>
                            @if ($alertTotal)
                                <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white ring-2 ring-white">{{ $alertTotal > 99 ? '99+' : $alertTotal }}</span>
                            @endif
                        </button>
                        <div x-show="open" x-cloak x-transition.origin.top.right class="absolute right-0 top-12 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                            <div class="border-b border-slate-100 px-4 py-3 text-sm font-semibold text-slate-900">Notifications</div>
                            @forelse ($alerts as [$label, $count, $href])
                                <a @if ($href) href="{{ $href }}" @endif class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50">
                                    <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                                    <span class="flex-1">{{ $label }}</span>
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $count }}</span>
                                </a>
                            @empty
                                <div class="px-4 py-6 text-center text-sm text-slate-500">You're all caught up.</div>
                            @endforelse
                        </div>
                    </div>

                    <span class="mx-1 hidden h-8 w-px bg-slate-200 sm:block"></span>

                    {{-- User --}}
                    <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                        <button @click="open = ! open" @click.outside="open = false" class="flex items-center gap-2.5 rounded-full py-1 pl-1 pr-2 transition hover:bg-slate-100">
                            <span class="relative">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-sky-500 text-xs font-semibold text-white ring-2 ring-white shadow-sm">{{ $initials }}</span>
                                <span class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-white" title="Online"></span>
                            </span>
                            <span class="hidden text-left leading-tight sm:block">
                                <span class="block text-sm font-semibold text-slate-900">{{ $user->name }}</span>
                                <span class="mt-0.5 inline-block rounded-full bg-indigo-50 px-2 py-px text-[10px] font-medium text-indigo-700">{{ $user->roleLabel() }}</span>
                            </span>
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition.origin.top.right class="absolute right-0 top-14 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                            <div class="flex items-center gap-3 bg-gradient-to-r from-indigo-50 to-sky-50 px-4 py-4">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-sky-500 text-sm font-semibold text-white">{{ $initials }}</span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-slate-900">{{ $user->name }}</span>
                                    <span class="block truncate text-xs text-slate-500">{{ $user->email }}</span>
                                </span>
                            </div>
                            @if ($user->last_login_at)
                                <div class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">Signed in {{ $user->last_login_at->diffForHumans() }}</div>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm font-medium text-red-600 hover:bg-red-50">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                                    Log out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <main class="flex-1 p-4 lg:p-6">
            @if (session('success'))
                <div class="no-print mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            @if (session('warning'))
                <div class="no-print mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
            @endif
            @if (session('error'))
                <div class="no-print mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="no-print mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <div class="font-medium">Please fix the following:</div>
                    <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            {{ $slot }}
        </main>
        <footer class="no-print border-t border-slate-200 px-4 py-3 lg:px-6">
            <x-dt-credit />
        </footer>
    </div>
</div>
{{-- Live field alerts for admins / managers: rep check-ins, orders and collections. --}}
@if ($user->can('field.monitor') && \Illuminate\Support\Facades\Route::has('field.live'))
    <div x-data="{
            items: [],
            since: null,
            init() {
                try { this.since = Number(localStorage.getItem('fieldSince')) || null; } catch (e) {}
                this.poll();
                setInterval(() => { if (!document.hidden) this.poll(); }, 30000);
            },
            async poll() {
                try {
                    const url = '{{ route('field.live') }}' + (this.since ? '?since=' + this.since : '');
                    const res = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (this.since) data.events.forEach((e) => this.show(e));
                    this.since = data.now;
                    try { localStorage.setItem('fieldSince', data.now); } catch (e) {}
                } catch (e) {}
            },
            show(e) {
                const id = e.type + e.at + Math.random();
                this.items.push({ ...e, id });
                setTimeout(() => this.items = this.items.filter((i) => i.id !== id), 15000);
            },
         }"
         class="no-print fixed right-4 bottom-4 z-50 flex w-80 flex-col gap-2">
        <template x-for="e in items" :key="e.id">
            <a :href="e.url" class="block rounded-lg border-l-4 bg-white p-3 text-sm shadow-lg"
               :class="{ 'border-emerald-500': e.type === 'checkin', 'border-slate-400': e.type === 'checkout', 'border-indigo-500': e.type === 'order', 'border-amber-500': e.type === 'payment' }">
                <div class="text-xs font-semibold uppercase text-slate-500"
                     x-text="{ checkin: 'Rep checked in', checkout: 'Rep checked out', order: 'New rep order', payment: 'Payment collected' }[e.type]"></div>
                <div x-text="e.text"></div>
            </a>
        </template>
    </div>
@endif
</body>
</html>
