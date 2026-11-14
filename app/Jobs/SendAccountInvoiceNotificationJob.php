<?php

namespace App\Jobs;

use App\Mail\AccountInvoiceNotificationMail;
use App\Models\Account\AccountInvoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAccountInvoiceNotificationJob extends BaseEmailJob
{
    public function __construct(
        public int $accountInvoiceId,
        public string $type
    ) {}

    protected function execute(): void
    {
        $invoice = AccountInvoice::with('account')->find($this->accountInvoiceId);
        if (!$invoice || !$invoice->account) {
            return;
        }

        $email = $invoice->account->billing_email ?? $invoice->account->owner_email;
        if (!$email) {
            Log::warning('Account has no billing or owner email', ['account_id' => $invoice->account_id]);
            return;
        }

        $this->recipient = $email;

        Mail::to($email)->send(new AccountInvoiceNotificationMail($invoice, $this->type));

        Log::info('Account invoice notification sent', [
            'invoice_id' => $this->accountInvoiceId,
            'type' => $this->type,
            'email' => $email,
        ]);
    }

    /**
     * The account's billing address, falling back to the owner's, looked up
     * again because failed() runs on a job rebuilt from the queue payload.
     *
     * @return string|null the address, or null when it no longer resolves
     */
    protected function resolveRecipient(): ?string
    {
        $invoice = AccountInvoice::with('account')->find($this->accountInvoiceId);

        return $invoice?->account?->billing_email ?? $invoice?->account?->owner_email;
    }
}
