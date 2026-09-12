<?php

namespace App\Services\Platform;

use App\Models\Account\AccountPaymentRequest;
use App\Services\Core\StorageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Turns a stored receipt path into something the console can actually show.
 *
 * `account_payment_requests.receipt_url` is an R2 object key, not a URL, and
 * the bucket is private — deliberately, since a receipt is a subscriber's bank
 * or e-wallet screenshot. Every read mints a fresh short-lived signed URL
 * instead, so a link copied out of the console stops working within minutes
 * and nothing is ever cached.
 *
 * This lives in a service rather than in the resource because minting a URL
 * signs a request against R2's credentials; doing that inside `toArray()`
 * would mean IO per row with no way to see or batch it.
 */
class PlatformReceiptService
{
    public function __construct(
        private StorageService $storageService,
    ) {
    }

    /**
     * Attach a `platform_receipt` payload to every request in the collection.
     *
     * @param Collection<int, AccountPaymentRequest> $requests
     *
     * @return Collection<int, AccountPaymentRequest>
     */
    public function attachReceipts(Collection $requests): Collection
    {
        $configured = $this->storageService->isR2Configured();

        foreach ($requests as $request) {
            $request->platform_receipt = $this->receiptFor($request, $configured);
        }

        return $requests;
    }

    /**
     * @param AccountPaymentRequest $request
     *
     * @return AccountPaymentRequest
     */
    public function attachReceipt(AccountPaymentRequest $request): AccountPaymentRequest
    {
        $request->platform_receipt = $this->receiptFor($request, $this->storageService->isR2Configured());

        return $request;
    }

    /**
     * Build one receipt payload.
     *
     * A receipt that cannot be signed still returns its file name and type with
     * a null URL: the operator should see that a receipt exists and that the
     * link could not be produced, rather than the row silently claiming there
     * is no receipt at all.
     *
     * @param AccountPaymentRequest $request
     * @param bool $storageConfigured
     *
     * @return array<string, mixed>|null
     */
    private function receiptFor(AccountPaymentRequest $request, bool $storageConfigured): ?array
    {
        $path = $request->receipt_url;

        if (!is_string($path) || $path === '') {
            return null;
        }

        $fileName = $request->receipt_file_name ?: basename($path);
        $ttl = (string) config('platform.receipt_url_ttl', '+15 minutes');
        $url = null;
        $expiresAt = null;

        // `receipt_url` is NOT reliably an object key. The gym app's own
        // uploadReceipt() stores `{R2_PUBLIC_URL}/{path}` — a complete, already
        // resolvable URL — and older rows can hold a legacy Firebase URL.
        // Presigning one of those signs a key literally named
        // "https://…", which resolves to nothing and 403s in the browser.
        // The gym app's getFileUrl() makes the same distinction; keep the two
        // in step.
        if (Str::startsWith($path, ['http://', 'https://'])) {
            $url = $path;
        } elseif ($storageConfigured) {
            try {
                $url = $this->storageService->createPresignedDownload($path, $ttl);
                $expiresAt = $url === null ? null : Carbon::parse($ttl)->toIso8601String();
            } catch (\Throwable $e) {
                Log::warning('Could not sign a platform receipt URL', [
                    'payment_request_id' => $request->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'url' => $url,
            'fileName' => $fileName,
            'mimeType' => $this->mimeTypeFor($fileName),
            // Null for a stored absolute URL: it is not signed, so it does not
            // expire — and telling the console otherwise would have it warn
            // about an expiry that never happens.
            'expiresAt' => $expiresAt,
        ];
    }

    /**
     * The content type is not stored, so it is inferred from the extension —
     * enough for the console to choose between an image and a PDF viewer.
     *
     * @param string $fileName
     *
     * @return string
     */
    private function mimeTypeFor(string $fileName): string
    {
        return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }
}
