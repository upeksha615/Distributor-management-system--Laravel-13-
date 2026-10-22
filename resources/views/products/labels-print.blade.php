@php $roll = request('layout') === 'roll'; @endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Labels</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #000; }
        @if ($roll)
            @page { size: 50mm 25mm; margin: 0; }
            .label { width: 50mm; height: 25mm; padding: 1.5mm 2mm; page-break-after: always; overflow: hidden; }
            .barcode svg { width: 46mm; height: 8mm; }
        @else
            @page { size: A4; margin: 10mm 0 0 0; }
            .sheet { display: grid; grid-template-columns: repeat(3, 70mm); grid-auto-rows: 37mm; }
            .label { padding: 3mm 4mm; overflow: hidden; }
            .barcode svg { width: 60mm; height: 11mm; }
        @endif
        .name { font-size: 9px; font-weight: bold; line-height: 1.15; max-height: 2.4em; overflow: hidden; }
        .meta { font-size: 8px; }
        .price { font-size: 12px; font-weight: bold; }
        .code { font-size: 8px; text-align: center; letter-spacing: 1px; }
        .row { display: flex; justify-content: space-between; align-items: baseline; }
        @media screen { body { background: #e2e8f0; padding: 10px; } .label { background: #fff; outline: 1px dashed #94a3b8; margin: 2px; } }
    </style>
</head>
<body onload="window.print()">
<div class="{{ $roll ? '' : 'sheet' }}">
    @foreach ($labels as $p)
        @php $code = $p->barcode ?: $p->sku; @endphp
        <div class="label">
            <div class="name">{{ $p->name }}</div>
            <div class="row meta"><span>{{ $p->sku }}{{ $p->brand ? ' · '.$p->brand->name : '' }}</span><span>{{ $p->location }}</span></div>
            <div class="barcode">{!! \App\Support\Barcode::svg($code) !!}</div>
            <div class="row"><span class="code">{{ $code }}</span>@if ($showPrice)<span class="price">{{ config('distributor.currency') }} {{ number_format($p->selling_price, 2) }}</span>@endif</div>
        </div>
    @endforeach
</div>
</body>
</html>
