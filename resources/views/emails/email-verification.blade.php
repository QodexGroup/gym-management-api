@component('mail::message')
@include('emails.partials.logo-header')

# Verify Your Email Address

Hello,

Thanks for signing up with **GymHubPH**! Please verify your email address to complete your registration and get started.

@component('mail::button', ['url' => $verificationUrl, 'color' => 'primary'])
Verify Email Address
@endcomponent

If the button doesn’t work, copy and paste this link into your browser:
<br>
<a href="{{ $verificationUrl }}">{{ $verificationUrl }}</a>

If you didn't create an account with GymHubPH, you can safely ignore this email.

Thanks,<br>
The **GymHubPH** Team
@endcomponent
