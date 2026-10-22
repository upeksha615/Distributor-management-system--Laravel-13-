<x-mail::message>
# Dear {{ $payment->customer->name }},

@if ($kind === 'verified')
@if ($payment->method === 'cheque' && ! $payment->isSettled())
We have received your cheque **{{ $payment->cheque_number }}** ({{ $payment->cheque_bank }}) for **{{ money($payment->amount) }}**.

It will be credited to your account **when the bank clears it**. Until then your outstanding balance does not change.
@else
We have received and confirmed your payment of **{{ money($payment->amount) }}** ({{ $payment->methodLabel() }}, {{ $payment->payment_date->format('d/m/Y') }}).
@endif

@if ($payment->allocations->isNotEmpty())
<x-mail::table>
| Applied to invoice | Amount |
|:--|--:|
@foreach ($payment->allocations as $a)
| {{ $a->invoice->number }} | {{ money($a->amount) }} |
@endforeach
</x-mail::table>
@endif
@elseif ($kind === 'rejected')
We could not confirm the payment of **{{ money($payment->amount) }}** ({{ $payment->methodLabel() }}, {{ $payment->payment_date->format('d/m/Y') }}, ref {{ $payment->number }}).

**Reason:** {{ $payment->rejection_reason ?: 'Not received in our bank account.' }}

Your account has not been credited. Please contact our office with your bank slip or reference.
@else
Your cheque **{{ $payment->cheque_number }}** ({{ $payment->cheque_bank }}) for **{{ money($payment->amount) }}** was returned by the bank on {{ $payment->returned_date?->format('d/m/Y') }}.

@if ($payment->return_reason)
**Reason given by the bank:** {{ $payment->return_reason }}
@endif

The amount has been added back to your outstanding balance. Please arrange a replacement payment and contact our office.
@endif

Thank you,<br>
{{ config('distributor.company.name') }}@if (config('distributor.company.phone'))<br>{{ config('distributor.company.phone') }}@endif
</x-mail::message>
