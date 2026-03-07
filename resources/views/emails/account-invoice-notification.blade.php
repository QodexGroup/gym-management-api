@component('mail::message')
# Subscription invoice: {{ $invoice->invoice_number }}

@if($type === 'issued')
Your invoice for **{{ $accountName }}** has been issued.

- **Plan:** {{ $invoice->plan_name }}
- **Billing period:** {{ $invoice->billing_period }}
- **Amount:** {{ number_format($invoice->plan_price, 2) }}

Payment is due by the **5th** of the billing month. If unpaid by the **10th**, your account will be locked.
@elseif($type === 'due_reminder')
Reminder: Invoice **{{ $invoice->invoice_number }}** is due on the **5th** of the month. Please pay to avoid account lock on the 10th.
@elseif($type === 'overdue')
Invoice **{{ $invoice->invoice_number }}** is now overdue. Please pay as soon as possible to avoid account lock on the 10th.
@else
Your account has been **locked** because invoice **{{ $invoice->invoice_number }}** was unpaid past the 10th. Please pay to restore access.
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
