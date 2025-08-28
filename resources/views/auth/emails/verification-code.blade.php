@component('mail::message')
# Verify Your Email Address

Please use the following verification code to complete your registration:

@component('mail::panel')
{{ $code }}
@endcomponent

The code will expire in 1 hour.

If you did not create an account, no further action is required.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
