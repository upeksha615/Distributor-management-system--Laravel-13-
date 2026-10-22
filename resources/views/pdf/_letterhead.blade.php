@php $company = config('distributor.company'); @endphp
<table class="letterhead">
    <tr>
        <td>
            <div class="company">{{ $company['name'] }}</div>
            @if ($company['address'])<div>{{ $company['address'] }}</div>@endif
            <div class="muted">{{ collect([$company['phone'], $company['email']])->filter()->join(' · ') }}</div>
            @if ($company['tax_number'])<div class="muted">Tax no: {{ $company['tax_number'] }}</div>@endif
        </td>
        <td class="right">
            <div class="doc-title">{{ $docTitle }}</div>
            @if (! empty($docNumber))<div class="bold">{{ $docNumber }}</div>@endif
            @if (! empty($docSubtitle))<div class="muted">{{ $docSubtitle }}</div>@endif
        </td>
    </tr>
</table>
