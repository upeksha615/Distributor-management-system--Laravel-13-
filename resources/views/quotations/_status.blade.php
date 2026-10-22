{{-- Quotation status badge (shows "Expired" for open quotes past their validity date). --}}
@php
    $label = $quote->isExpired() ? 'expired' : $quote->status;
    $colors = [
        'open' => 'bg-amber-100 text-amber-800',
        'accepted' => 'bg-blue-100 text-blue-700',
        'rejected' => 'bg-red-100 text-red-700',
        'converted' => 'bg-emerald-100 text-emerald-700',
        'expired' => 'bg-slate-200 text-slate-500',
    ];
@endphp
<span class="badge {{ $colors[$label] ?? 'bg-slate-100 text-slate-700' }}">{{ ucfirst($label) }}</span>
