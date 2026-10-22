@props(['placeholder' => 'Search...'])
{{-- GET filter form. Put extra filter inputs in the slot. --}}
<form method="GET" class="card card-body mb-4 flex flex-wrap items-end gap-3 no-print">
    <div class="min-w-48 flex-1">
        <label class="label">Search</label>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $placeholder }}" class="input">
    </div>
    {{ $slot }}
    <button class="btn btn-secondary">Filter</button>
    @if (request()->hasAny(['q', 'status', 'from', 'to', 'method', 'type', 'warehouse_id', 'brand_id', 'category_id', 'supplier_id']))
        <a href="{{ url()->current() }}" class="btn btn-secondary">Clear</a>
    @endif
</form>
