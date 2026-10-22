{{--
    Stock availability colour: red = none, yellow = up to the low-stock threshold, green = more.
    The number is shown only to users with stock.view_qty (sales reps see the colour and label only).
--}}
@props(['qty', 'showQty' => null])
@php
    $showQty = ($showQty ?? true) && auth()->user()?->can('stock.view_qty');
    $level = \App\Models\Product::stockLevel((float) $qty);
    $styles = ['out' => 'bg-red-100 text-red-700 ring-red-200', 'low' => 'bg-yellow-100 text-yellow-800 ring-yellow-300', 'ok' => 'bg-green-100 text-green-800 ring-green-300'];
    $labels = ['out' => 'Out of stock', 'low' => 'Low', 'ok' => 'In stock'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 tabular-nums '.$styles[$level]]) }}
      title="{{ $labels[$level] }}">
    <span class="h-2 w-2 rounded-full {{ ['out' => 'bg-red-500', 'low' => 'bg-yellow-500', 'ok' => 'bg-green-500'][$level] }}"></span>
    {{ $showQty ? qty($qty) : $labels[$level] }}
</span>
