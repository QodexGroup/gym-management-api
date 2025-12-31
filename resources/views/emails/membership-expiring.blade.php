@component('mail::message')
# Membership Expiring Soon

Hello {{ $customerName }},

Your **{{ $membershipPlan }}** membership is expiring soon!

@component('mail::panel')
**Expiration Date:** {{ $expirationDate }}  
**Days Remaining:** {{ $daysRemaining }} days
@endcomponent

Don't let your fitness journey stop! Renew your membership today to continue enjoying all the benefits of our gym.

@component('mail::button', ['url' => ''])
Contact Us to Renew
@endcomponent

If you have any questions or need assistance with renewal, please don't hesitate to contact us.

Thank you for being a valued member!

Best regards,  
{{ config('app.name') }}
@endcomponent
