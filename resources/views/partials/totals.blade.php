<div class="mt-4 flex justify-end">
    <dl class="w-full max-w-xs space-y-1 text-sm">
        <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="tabular-nums">{{ money($doc->subtotal, false) }}</dd></div>
        @if ($doc->discount > 0)
            <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="tabular-nums">-{{ money($doc->discount, false) }}</dd></div>
        @endif
        @if ($doc->tax > 0)
            <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd class="tabular-nums">{{ money($doc->tax, false) }}</dd></div>
        @endif
        <div class="flex justify-between border-t border-slate-200 pt-1 text-base font-semibold"><dt>Total</dt><dd class="tabular-nums">{{ money($doc->total) }}</dd></div>
    </dl>
</div>
