{{-- Upcoming shipment (pending, not in stock yet), light green. Quantity only for users with stock.view_qty. --}}
@props(['qty', 'date' => null])
@if ((float) $qty > 0)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200 tabular-nums']) }}
          title="Upcoming shipment – not in stock yet">
        ⛴ {{ auth()->user()?->can('stock.view_qty') ? qty($qty) : 'Coming' }}@if ($date)<span class="font-normal text-emerald-600">· {{ \Illuminate\Support\Carbon::parse($date)->format('d M') }}</span>@endif
    </span>
@else
    <span class="text-xs text-slate-300">–</span>
@endif
