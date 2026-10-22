<x-mail::message>
# Dear {{ $customer->name }},

According to our records the following invoices are past their due date:

<x-mail::table>
| Invoice | Date | Due | Balance |
|:--|:--|:--|--:|
@foreach ($invoices as $inv)
| {{ $inv->number }} | {{ $inv->invoice_date->format('d/m/Y') }} | {{ $inv->due_date->format('d/m/Y') }} ({{ (int) $inv->due_date->diffInDays(today()) }} days) | {{ money($inv->balance()) }} |
@endforeach
| **Total overdue** | | | **{{ money($total) }}** |
</x-mail::table>

@if ($pending > 0)
We have also received payments of {{ money($pending) }} that are still being verified or are cheques awaiting clearance. They will be applied once confirmed.
@endif

Please arrange payment at your earliest convenience. If you have already paid, kindly send us the bank slip so we can match it.

Thank you,<br>
{{ config('distributor.company.name') }}@if (config('distributor.company.phone'))<br>{{ config('distributor.company.phone') }}@endif
</x-mail::message>
