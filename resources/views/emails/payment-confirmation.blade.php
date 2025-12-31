@component('mail::message')
# Payment Confirmation

Hello {{ $customerName }},

Thank you for your payment! We have successfully received your payment.

@component('mail::panel')
**Amount Paid:** ₱{{ $amount }}  
**Payment Method:** {{ $paymentMethod }}  
**Payment Date:** {{ $paymentDate }}  
**Reference Number:** {{ $referenceNumber }}
@endcomponent

## Bill Summary

- **Total Bill Amount:** ₱{{ $billAmount }}
- **Remaining Balance:** ₱{{ $remainingBalance }}

@if($remainingBalance > 0)
You still have a remaining balance of **₱{{ $remainingBalance }}**. Please settle this at your earliest convenience.
@else
Your bill has been fully paid. Thank you!
@endif

@component('mail::button', ['url' => ''])
View Receipt
@endcomponent

If you have any questions about this payment, please contact us.

Thank you for your continued support!

Best regards,  
{{ config('app.name') }}
@endcomponent
