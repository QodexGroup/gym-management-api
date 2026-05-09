@component('mail::message')
<div style="text-align: center; margin-bottom: 24px;">
<img src="{{ $logoUrl }}" alt="GymHubPH" width="160" style="max-width: 160px; height: auto;" />
</div>

# Reset Your Password

Hello,

We received a request to reset the password for your **GymHubPH** account. Click the button below to choose a new password.

@component('mail::button', ['url' => $resetUrl, 'color' => 'primary'])
Reset Password
@endcomponent

If the button doesn’t work, copy and paste this link into your browser:
<br>
<a href="{{ $resetUrl }}">{{ $resetUrl }}</a>

If you didn’t request a password reset, you can safely ignore this email.

Thanks,<br>
The **GymHubPH** Team
@endcomponent
