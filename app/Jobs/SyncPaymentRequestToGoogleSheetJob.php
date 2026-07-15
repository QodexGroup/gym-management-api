<?php

namespace App\Jobs;

use App\Models\Account\AccountInvoice;
use App\Models\Account\AccountPaymentRequest;
use App\Models\Account\AccountSubscriptionPlan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Appends a newly submitted payment request as a row in a Google Sheet
 * via a Google Apps Script webhook (see docs/google-sheets-payment-sync.md).
 *
 * No-ops when services.google_sheets.payment_webhook_url is not configured.
 */
class SyncPaymentRequestToGoogleSheetJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public int $paymentRequestId) {}

    public function middleware(): array
    {
        return [new RateLimited('google-sheets')];
    }

    public function handle(): void
    {
        $webhookUrl = (string) config('services.google_sheets.payment_webhook_url');
        if ($webhookUrl === '') {
            return;
        }

        $request = AccountPaymentRequest::with(['account', 'createdByUser'])
            ->find($this->paymentRequestId);

        if (!$request) {
            return;
        }

        $requestedBy = $request->createdByUser
            ? trim($request->createdByUser->firstname . ' ' . $request->createdByUser->lastname)
            : '';

        $response = Http::timeout(15)->post($webhookUrl, [
            'secret' => (string) config('services.google_sheets.webhook_secret'),
            'submittedAt' => $request->created_at?->format('Y-m-d H:i:s'),
            'paymentRequestId' => $request->id,
            'accountId' => $request->account_id,
            'accountName' => $request->account->account_name ?? '',
            'paymentFor' => $this->paymentTransactionLabel($request),
            'amount' => (float) $request->amount,
            'paymentType' => $request->payment_type,
            'status' => $request->status,
            'receiptUrl' => $request->receipt_url,
            'receiptFileName' => $request->receipt_file_name ?? '',
            'requestedBy' => $requestedBy,
        ]);

        if (!$response->successful()) {
            Log::error('Google Sheet payment sync failed', [
                'payment_request_id' => $this->paymentRequestId,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            throw new \RuntimeException('Google Sheet webhook returned HTTP ' . $response->status());
        }
    }

    private function paymentTransactionLabel(AccountPaymentRequest $request): string
    {
        return match ($request->payment_transaction) {
            AccountInvoice::class => 'Invoice',
            AccountSubscriptionPlan::class => 'Subscription Upgrade',
            default => (string) $request->payment_transaction,
        };
    }
}
