<x-mail::message>
# Dear {{ $invoice->customer->name }},

Please find attached invoice **{{ $invoice->number }}** dated {{ $invoice->invoice_date->format('d/m/Y') }}.

<x-mail::table>
| | |
|:--|--:|
| Invoice total | {{ money($invoice->total) }} |
@if ($invoice->paid_amount + $invoice->credited_amount > 0)
| Paid / credited | {{ money($invoice->paid_amount + $invoice->credited_amount) }} |
@endif
| **Balance due** | **{{ money($invoice->balance()) }}** |
| Due date | {{ $invoice->due_date->format('d/m/Y') }} |
</x-mail::table>

@if ($note)
{{ $note }}
@endif

Thank you for your business,<br>
{{ config('distributor.company.name') }}@if (config('distributor.company.phone'))<br>{{ config('distributor.company.phone') }}@endif
</x-mail::message>
