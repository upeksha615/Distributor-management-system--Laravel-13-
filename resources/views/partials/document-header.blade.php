{{-- Company letterhead for printable documents. --}}
@php $company = config('distributor.company'); @endphp
<div class="mb-6 flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
    <div>
        <div class="text-lg font-bold text-slate-900">{{ $company['name'] }}</div>
        @if ($company['address'])<div class="text-sm text-slate-600">{{ $company['address'] }}</div>@endif
        <div class="text-sm text-slate-600">{{ collect([$company['phone'], $company['email']])->filter()->join(' · ') }}</div>
        @if ($company['tax_number'])<div class="text-sm text-slate-600">Tax no: {{ $company['tax_number'] }}</div>@endif
    </div>
    <div class="text-right">
        <div class="text-xl font-bold tracking-wide text-slate-900">{{ $docTitle }}</div>
        <div class="text-sm font-medium text-slate-600">{{ $docNumber }}</div>
    </div>
</div>
