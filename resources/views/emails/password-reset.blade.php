<x-mail::message>
# Hello {{ $user->name }},

We received a request to reset the password for your account ({{ $user->email }}).

<x-mail::button :url="$url">
Reset password
</x-mail::button>

This link expires in {{ $minutes }} minutes and can be used once.

If you did not ask for this, ignore this email – your password will not change.

{{ config('distributor.company.name') }}
</x-mail::message>
