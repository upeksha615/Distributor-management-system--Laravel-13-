{{--
    Generic printable document (invoice, PO, GRN, credit note, receipt, sales order, statement).
    $docTitle, $docNumber
    $party: ['label' => 'Bill to', 'lines' => [...]]           (left block)
    $meta: ['Invoice date' => '01/01/2026', ...]                 (right block)
    $columns: ['Heading' => 'text'|'money'|'qty'|'number']
    $rows: arrays of values; $totals: ['Subtotal' => 100, ...] last entry shown bold
    $notes, $signatures: ['Authorised signature', ...]
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('pdf._style')
</head>
<body>
    @include('pdf._letterhead')

    <table style="width: 100%; margin-bottom: 8px;">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                @if (! empty($party))
                    <div class="muted bold" style="font-size: 8px; text-transform: uppercase;">{{ $party['label'] }}</div>
                    @foreach ($party['lines'] as $i => $line)
                        @if ($line)<div class="{{ $i === 0 ? 'bold' : '' }}">{{ $line }}</div>@endif
                    @endforeach
                @endif
            </td>
            <td style="vertical-align: top;">
                <table class="meta" style="margin-left: auto;">
                    @foreach ($meta ?? [] as $label => $value)
                        <tr><td class="muted">{{ $label }}</td><td>{{ $value }}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    @php $types = array_values($columns); @endphp
    <table class="data">
        <thead><tr>@foreach ($columns as $heading => $type)<th class="{{ $type !== 'text' ? 'num' : '' }}">{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>@foreach (array_values($row) as $i => $value)<td class="{{ $types[$i] !== 'text' ? 'num' : '' }}">{{ \App\Support\ExportFormat::cell($value, $types[$i]) }}</td>@endforeach</tr>
        @endforeach
        </tbody>
    </table>

    @if (! empty($totals))
        <table class="totals">
            @foreach ($totals as $label => $value)
                <tr class="{{ $loop->last ? 'grand' : '' }}">
                    <td>{{ $label }}</td>
                    <td class="right">{{ $loop->last ? config('distributor.currency').' ' : '' }}{{ is_numeric($value) ? number_format((float) $value, 2) : $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (! empty($notes))<p><span class="muted">Notes:</span> {{ $notes }}</p>@endif

    @if (! empty($signatures))
        <table class="signatures"><tr>@foreach ($signatures as $s)<td><div>{{ $s }}</div></td>@endforeach</tr></table>
    @endif
</body>
</html>
