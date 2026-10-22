<x-layouts.app title="Dashboard">
@php
    $u = auth()->user();
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $tone = [
        'indigo' => 'bg-indigo-50 text-indigo-600 ring-indigo-100',
        'violet' => 'bg-violet-50 text-violet-600 ring-violet-100',
        'sky' => 'bg-sky-50 text-sky-600 ring-sky-100',
        'teal' => 'bg-teal-50 text-teal-600 ring-teal-100',
        'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-100',
        'amber' => 'bg-amber-50 text-amber-600 ring-amber-100',
        'rose' => 'bg-rose-50 text-rose-600 ring-rose-100',
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
    $waiting = collect($tasks)->sum('count');

    // Calendar grid for this month, weeks starting Monday.
    $holidaysByDay = $monthHolidays->groupBy(fn ($h) => $h->date->day);
    $lead = $month->dayOfWeekIso - 1;
    $days = $month->daysInMonth;
@endphp

    {{-- Welcome + search --}}
    <section class="relative mb-6 overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-r from-indigo-50 via-white to-sky-50 px-6 py-6">
        <div class="pointer-events-none absolute -right-10 -top-16 h-48 w-48 rounded-full bg-indigo-100/60 blur-2xl"></div>
        <div class="relative flex flex-wrap items-end justify-between gap-6">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 sm:text-2xl">{{ $greeting }}, {{ \Illuminate\Support\Str::of($u->name)->before(' ') }} 👋</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ now()->format('l, j F Y') }}
                    @if ($todayHoliday = $monthHolidays->first(fn ($h) => $h->date->isToday())) · <span class="font-medium text-amber-700">{{ $todayHoliday->name }}</span>@endif
                </p>
            </div>
            @if ($u->can('products.view'))
                <x-part-search size="lg" placeholder="Search by part number, product name or vehicle…" class="w-full max-w-xl lg:w-[28rem]" />
            @endif
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Quick actions --}}
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm xl:col-span-2">
            <h2 class="font-semibold text-slate-800">Quick actions</h2>
            <p class="text-xs text-slate-400">Your everyday jobs, one click away</p>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @forelse ($actions as $a)
                    <a href="{{ $a['href'] }}" class="group flex flex-col items-center gap-2.5 rounded-xl border border-slate-100 px-3 py-4 text-center transition hover:-translate-y-0.5 hover:border-slate-200 hover:shadow-md">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl ring-1 transition group-hover:scale-105 {{ $tone[$a['tone']] }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $a['icon'] }}"/></svg>
                        </span>
                        <span class="text-sm font-medium text-slate-700">{{ $a['label'] }}</span>
                    </a>
                @empty
                    <p class="col-span-full py-6 text-center text-sm text-slate-400">No shortcuts for your role.</p>
                @endforelse
            </div>
        </section>

        {{-- My tasks --}}
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-slate-800">My tasks</h2>
                    <p class="text-xs text-slate-400">Waiting for you or your team</p>
                </div>
                @if ($waiting)<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $waiting }} waiting</span>@endif
            </div>
            <div class="mt-4 space-y-2">
                @php $open = collect($tasks)->where('count', '>', 0); @endphp
                @forelse ($open as $t)
                    <a @if ($t['href']) href="{{ $t['href'] }}" @endif class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5 transition hover:border-slate-200 hover:bg-slate-50">
                        <span class="h-2 w-2 shrink-0 rounded-full {{ ['amber' => 'bg-amber-400', 'teal' => 'bg-teal-400', 'sky' => 'bg-sky-400', 'violet' => 'bg-violet-400', 'rose' => 'bg-rose-400'][$t['tone']] ?? 'bg-slate-300' }}"></span>
                        <span class="flex-1 text-sm text-slate-600">{{ $t['label'] }}</span>
                        <span class="min-w-8 rounded-lg px-2 py-0.5 text-center text-sm font-semibold ring-1 {{ $tone[$t['tone']] }}">{{ $t['count'] }}</span>
                    </a>
                @empty
                    <div class="flex flex-col items-center rounded-xl bg-emerald-50/60 px-4 py-8 text-center">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg></span>
                        <p class="mt-2 text-sm font-medium text-emerald-700">All clear</p>
                        <p class="text-xs text-emerald-600/80">Nothing is waiting right now.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        {{-- Notice board --}}
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm xl:col-span-2">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-slate-800">Notice board</h2>
                    <p class="text-xs text-slate-400">Messages from the office</p>
                </div>
                @if ($u->can('notices.manage'))
                    <a href="{{ route('notices.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">Manage notices →</a>
                @endif
            </div>
            <div class="mt-4 space-y-3">
                @forelse ($notices as $n)
                    <article class="rounded-xl border-l-4 px-4 py-3 {{ $n->level === 'important' ? 'border-rose-400 bg-rose-50/60' : 'border-indigo-300 bg-slate-50' }}">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($n->level === 'important')<span class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-rose-700">Important</span>@endif
                            <h3 class="text-sm font-semibold text-slate-800">{{ $n->title }}</h3>
                        </div>
                        @if ($n->body)<p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $n->body }}</p>@endif
                        <p class="mt-1.5 text-xs text-slate-400">{{ $n->starts_on->format('d M Y') }}{{ $n->ends_on ? ' – '.$n->ends_on->format('d M Y') : '' }}</p>
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400">
                        No notices right now.
                        @if ($u->can('notices.manage'))<a href="{{ route('notices.index') }}" class="font-medium text-indigo-600 hover:text-indigo-700">Post one</a>@endif
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Calendar --}}
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-slate-800">{{ $month->format('F Y') }}</h2>
                @if ($u->can('notices.manage'))<a href="{{ route('holidays.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-700">Holidays →</a>@endif
            </div>
            <div class="mt-4 grid grid-cols-7 gap-1 text-center text-[11px] font-medium uppercase text-slate-400">
                @foreach (['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'] as $d)<div>{{ $d }}</div>@endforeach
            </div>
            <div class="mt-1 grid grid-cols-7 gap-1 text-center text-sm">
                @for ($i = 0; $i < $lead; $i++)<div></div>@endfor
                @for ($d = 1; $d <= $days; $d++)
                    @php
                        $date = $month->copy()->day($d);
                        $hol = $holidaysByDay->get($d);
                        $isPoya = $hol && $hol->contains(fn ($h) => $h->isPoya());
                    @endphp
                    <div title="{{ $hol ? $hol->pluck('name')->join(', ') : '' }}"
                         @class([
                             'relative flex h-9 items-center justify-center rounded-lg',
                             'bg-indigo-600 font-semibold text-white shadow-sm' => $date->isToday(),
                             'bg-amber-50 font-medium text-amber-700 ring-1 ring-amber-200' => $hol && ! $date->isToday(),
                             'text-slate-400' => ! $hol && ! $date->isToday() && $date->isWeekend(),
                             'text-slate-700' => ! $hol && ! $date->isToday() && ! $date->isWeekend(),
                         ])>
                        {{ $d }}
                        @if ($hol)<span class="absolute bottom-1 left-1/2 h-1 w-1 -translate-x-1/2 rounded-full {{ $isPoya ? 'bg-amber-400' : 'bg-rose-400' }}"></span>@endif
                    </div>
                @endfor
            </div>

            <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-400">Upcoming holidays</h3>
            <ul class="mt-2 space-y-2">
                @forelse ($upcomingHolidays as $h)
                    @php $in = (int) today()->diffInDays($h->date); @endphp
                    <li class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-lg bg-amber-50 leading-none text-amber-700 ring-1 ring-amber-100">
                            <span class="text-[10px] font-semibold uppercase">{{ $h->date->format('M') }}</span>
                            <span class="text-sm font-bold">{{ $h->date->format('j') }}</span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-slate-700">{{ $h->name }}</span>
                            <span class="block text-xs text-slate-400">{{ $h->date->format('l') }} · {{ $h->typesLabel() }} · {{ $in === 0 ? 'today' : 'in '.$in.' '.\Illuminate\Support\Str::plural('day', $in) }}</span>
                        </span>
                    </li>
                @empty
                    <li class="text-sm text-slate-400">No upcoming holidays entered.</li>
                @endforelse
            </ul>
        </section>
    </div>

    {{-- System status (administrators) --}}
    @if ($system)
        @php
            $items = [
                ['Scheduler', $system['scheduler'] ? 'Running' : 'Not running', $system['scheduler']],
                ['Outgoing email', $system['mail'] ? ($system['failed_emails'] ? $system['failed_emails'].' failed this week' : 'Working') : 'Not set up (log only)', $system['mail'] && ! $system['failed_emails']],
            ];
        @endphp
        <section class="mt-6 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-slate-800">System status</h2>
                <a href="{{ route('system.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">Scheduled tasks →</a>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($items as [$label, $text, $ok])
                    <div class="flex items-center gap-3 rounded-xl border px-4 py-3 {{ $ok ? 'border-emerald-100 bg-emerald-50/50' : 'border-amber-200 bg-amber-50/60' }}">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full {{ $ok ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-700' }}">
                            @if ($ok)
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            @else
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.01"/></svg>
                            @endif
                        </span>
                        <span><span class="block text-xs text-slate-500">{{ $label }}</span><span class="block text-sm font-medium text-slate-800">{{ $text }}</span></span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
