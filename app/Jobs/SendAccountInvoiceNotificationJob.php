<?php

namespace App\Jobs;

use App\Mail\AccountInvoiceNotificationMail;
use App\Models\Account\AccountInvoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAccountInvoiceNotificationJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 30;

    public function __construct(
        public int $accountInvoiceId,
        public string $type
    ) {
    }

    public function handle(): void
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

        try {
            Mail::to($email)->send(new AccountInvoiceNotificationMail($invoice, $this->type));
            Log::info('Account invoice notification sent', [
                'invoice_id' => $this->accountInvoiceId,
                'type' => $this->type,
                'email' => $email,
            ]);
        } catch (\Throwable $th) {
            Log::error('Account invoice notification failed', [
                'invoice_id' => $this->accountInvoiceId,
                'type' => $this->type,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }
}
