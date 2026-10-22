<x-mail::message>
# Test email

This test was sent from {{ config('app.url') }} at {{ now()->format('d/m/Y H:i') }}.

If you are reading this, outgoing email works.

{{ config('distributor.company.name') }}
</x-mail::message>
