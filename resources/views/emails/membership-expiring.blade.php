@component('mail::message')
@include('emails.partials.logo-header')

# Membership Expiring Soon

Hello {{ $customerName }},

Your **{{ $membershipPlan }}** membership is expiring soon!

@component('mail::panel')
**Expiration Date:** {{ $expirationDate }}  
**Days Remaining:** {{ $daysRemainingLabel }}
@endcomponent

Don't let your fitness journey stop! Renew your membership today to continue enjoying all the benefits of our gym.

To renew, simply drop by the gym or message our team - we'll take care of the rest for you.

If you have any questions or need assistance with renewal, please don't hesitate to contact us.

Thank you for being a valued member!

Best regards,  
{{ config('app.name') }}
@endcomponent
