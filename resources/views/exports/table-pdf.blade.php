<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('pdf._style')
</head>
<body>
    @include('pdf._letterhead', ['docTitle' => strtoupper($title), 'docSubtitle' => trim(($subtitle ? $subtitle.' · ' : '').'Printed '.now()->format('d/m/Y H:i'))])

    @php $types = array_values($columns); @endphp
    <table class="data">
        <thead>
        <tr>@foreach ($columns as $heading => $type)<th class="{{ $type !== 'text' ? 'num' : '' }}">{{ $heading }}</th>@endforeach</tr>
        </thead>
        <tbody>
        @forelse ($rows as $row)
            <tr>
                @foreach ($row as $i => $value)
                    <td class="{{ $types[$i] !== 'text' ? 'num' : '' }}">{{ \App\Support\ExportFormat::cell($value, $types[$i]) }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($columns) }}" class="muted">No records.</td></tr>
        @endforelse
        @if ($totals)
            <tr class="total">
                @foreach ($totals as $i => $value)<td class="{{ $types[$i] !== 'text' ? 'num' : '' }}">{{ \App\Support\ExportFormat::cell($value, $types[$i]) }}</td>@endforeach
            </tr>
        @endif
        </tbody>
    </table>
    <p class="muted">{{ count($rows) }} record(s)</p>
</body>
</html>
