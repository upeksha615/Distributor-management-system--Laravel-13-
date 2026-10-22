<x-mail::message>
# Daily summary – {{ today()->format('l, d/m/Y') }}

<x-mail::table>
| Needs attention | Count | Amount |
|:--|--:|--:|
@foreach ($summary['rows'] as $row)
| {{ $row['label'] }} | {{ $row['count'] }} | {{ $row['amount'] !== null ? money($row['amount']) : '' }} |
@endforeach
</x-mail::table>

**Yesterday:** sales {{ money($summary['sales_yesterday']) }} · payments settled {{ money($summary['collected_yesterday']) }}

<x-mail::button :url="route('dashboard')">
Open the dashboard
</x-mail::button>

{{ config('distributor.company.name') }}
</x-mail::message>
