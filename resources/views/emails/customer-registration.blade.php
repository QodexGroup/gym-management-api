@component('mail::message')
# Welcome to Our Gym!

Hello {{ $customerName }},

Welcome to our fitness family! We're thrilled to have you join us on your fitness journey.

@if($hasMembership)
@component('mail::panel')
**Membership Plan:** {{ $membershipPlan }}  
**Start Date:** {{ $membershipStartDate }}  
**End Date:** {{ $membershipEndDate }}
@endcomponent

Your membership is now active! You can start using all our facilities and services right away.
@else
We're excited to have you registered with us. Please contact us to set up your membership plan.
@endif

## What's Next?

- Visit our gym during operating hours
- Meet with our trainers to create your personalized workout plan
- Explore all our facilities and equipment
- Join our group classes and community events

@component('mail::button', ['url' => ''])
Visit Our Website
@endcomponent

If you have any questions or need assistance getting started, our team is here to help!

Let's achieve your fitness goals together!

Best regards,  
{{ config('app.name') }}
@endcomponent
