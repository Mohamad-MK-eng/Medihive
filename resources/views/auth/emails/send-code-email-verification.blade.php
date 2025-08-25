@component('mail::message')
# Email Verification Code

Hello!

Your email verification code is:

@component('mail::panel')
## {{ $code }}
@endcomponent

This code will expire in 1 hour. If you didn't request this, please ignore this email.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
