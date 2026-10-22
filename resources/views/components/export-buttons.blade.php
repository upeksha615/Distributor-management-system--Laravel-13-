@can('reports.export')
{{-- Download the current list (with its filters) as Excel, CSV or PDF. --}}
<div class="inline-flex overflow-hidden rounded-md border border-slate-300 bg-white text-sm no-print">
    <span class="border-r border-slate-300 bg-slate-50 px-2.5 py-2 text-slate-500">Export</span>
    @foreach (['xlsx' => 'Excel', 'csv' => 'CSV', 'pdf' => 'PDF'] as $format => $label)
        <a href="{{ request()->fullUrlWithQuery(['export' => $format, 'page' => null]) }}"
           class="px-2.5 py-2 text-slate-700 hover:bg-slate-50 {{ ! $loop->last ? 'border-r border-slate-300' : '' }}">{{ $label }}</a>
    @endforeach
</div>
@endcan
