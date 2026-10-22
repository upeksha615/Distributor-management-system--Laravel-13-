@props(['title', 'subtitle' => null])
<div class="mb-5 flex flex-wrap items-start justify-between gap-3 no-print">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>@endif
    </div>
    <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
</div>
