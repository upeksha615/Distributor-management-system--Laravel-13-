@props(['size' => 'sm', 'placeholder' => 'Search parts by number, name or vehicle…'])
{{-- Live parts search: suggestions appear while typing; Enter on a suggestion opens it, otherwise shows all results. --}}
@php $lg = $size === 'lg'; @endphp
<form method="GET" action="{{ route('products.index') }}" role="search"
      x-data="partSearch({ lookupUrl: @js(route('products.lookup')), showUrl: @js(route('products.show', '__ID__')) })"
      @click.outside="open = false" @keydown.escape="open = false"
      {{ $attributes->merge(['class' => 'relative']) }}>
    <div class="relative">
        <svg x-show="! loading" class="pointer-events-none absolute top-1/2 -translate-y-1/2 text-slate-400 {{ $lg ? 'left-4 h-5 w-5' : 'left-3 h-4 w-4' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.2-5.2m0 0A7.5 7.5 0 1 0 5.2 5.2a7.5 7.5 0 0 0 10.6 10.6Z"/></svg>
        <svg x-show="loading" x-cloak class="pointer-events-none absolute top-1/2 -translate-y-1/2 animate-spin text-indigo-500 {{ $lg ? 'left-4 h-5 w-5' : 'left-3 h-4 w-4' }}" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/></svg>
        <input name="q" type="search" data-no-live autocomplete="off" placeholder="{{ $placeholder }}" aria-label="Search parts"
               x-model="query" @input.debounce.250ms="search()" @focus="results.length && (open = true)"
               @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter="enter($event)"
               @class([
                   'w-full border border-slate-200 text-sm text-slate-800 placeholder:text-slate-400 focus:border-indigo-300 focus:outline-none focus:ring-4 focus:ring-indigo-100',
                   'rounded-xl bg-white py-3 pl-12 pr-24 shadow-sm' => $lg,
                   'rounded-full bg-slate-50 py-2 pl-9 pr-4 focus:bg-white' => ! $lg,
               ])>
        @if ($lg)
            <button class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Search</button>
        @endif
    </div>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
         class="absolute left-0 right-0 z-50 mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white text-left shadow-xl">
        <template x-if="results.length === 0">
            <div class="px-4 py-6 text-center text-sm text-slate-500">No parts match “<span x-text="query"></span>”.</div>
        </template>
        <ul class="max-h-96 overflow-y-auto py-1">
            <template x-for="(p, i) in results" :key="p.id">
                <li>
                    <a :href="@js(route('products.show', '__ID__')).replace('__ID__', p.id)" @mouseenter="highlighted = i"
                       :class="highlighted === i ? 'bg-indigo-50' : ''" class="flex items-center gap-3 px-4 py-2.5">
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="font-mono text-xs font-semibold text-indigo-700" x-text="p.sku"></span>
                                <span class="truncate text-sm font-medium text-slate-800" x-text="p.name"></span>
                            </span>
                            <span class="block truncate text-xs text-slate-500" x-text="[p.brand, p.part_number ? 'Part no ' + p.part_number : null, p.made_in].filter(Boolean).join(' · ')"></span>
                        </span>
                        <span x-show="p.incoming" class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-200"
                              x-text="p.incoming === true ? '⛴ Coming' : '⛴ ' + p.incoming"></span>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="stockClass(p.stock_level)" x-text="stockText(p)"></span>
                    </a>
                </li>
            </template>
        </ul>
        <button x-show="results.length" class="block w-full border-t border-slate-100 bg-slate-50 px-4 py-2.5 text-center text-xs font-medium text-indigo-600 hover:bg-slate-100">
            See all results for “<span x-text="query"></span>” →
        </button>
    </div>
</form>
