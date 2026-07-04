<?php

namespace App\Observers;

use App\Jobs\SyncPaymentRequestToGoogleSheetJob;
use App\Models\Account\AccountPaymentRequest;

class AccountPaymentRequestObserver
{
    /**
     * Mirror every newly submitted payment request to the Google Sheet.
     * Skipped entirely when the webhook URL is not configured (e.g. tests).
     */
    public function created(AccountPaymentRequest $paymentRequest): void
    {
        if ((string) config('services.google_sheets.payment_webhook_url') === '') {
            return;
        }

        SyncPaymentRequestToGoogleSheetJob::dispatch($paymentRequest->id);
    }
}
