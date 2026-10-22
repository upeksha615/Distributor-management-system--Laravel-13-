{{-- System developer credit: DT Solutions. The tile shows only the "DT" mark of the logo so it stays readable when small. --}}
<div {{ $attributes->merge(['class' => 'flex items-center justify-center gap-2 text-xs text-slate-500']) }}>
    <span>Developed by</span>
    <span class="inline-flex items-center gap-2">
        <span role="img" aria-label="DT Solutions logo" class="inline-block h-9 w-16 rounded-lg bg-white bg-no-repeat shadow-sm ring-1 ring-slate-200"
              style="background-image: url('{{ asset('images/dt-solutions-logo.webp') }}'); background-size: 78px 78px; background-position: -7px -12px;"></span>
        <span class="text-sm font-semibold text-slate-800">DT Solutions</span>
    </span>
</div>
