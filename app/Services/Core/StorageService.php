<?php

namespace App\Services\Core;

use App\Constant\ResourceKeyConstant;
use App\Constant\StorageConstant;
use App\Data\StorageUsage;
use App\Exceptions\QuotaExceededException;
use App\Models\Account\Account;
use App\Repositories\Core\StorageRepository;
use App\Services\Account\AccountUsageService;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Log;

/**
 * Storage-specific wrapper over the generic AccountUsageService.
 * Owns R2 concerns (presigning, listing) and KB/MB/GB formatting; delegates
 * all counter persistence to the account_usages meter (resource: storage).
 */
class StorageService
{
    private const RESOURCE = ResourceKeyConstant::STORAGE;

    /**
     * @param AccountUsageService $accountUsageService
     * @param StorageRepository $storageRepository
     */
    public function __construct(
        private AccountUsageService $accountUsageService,
        private StorageRepository $storageRepository,
    ) {}

    /**
     * Current storage quota snapshot for an account.
     *
     * @param int $accountId
     * @return StorageUsage
     */
    public function getUsage(int $accountId): StorageUsage
    {
        return $this->buildUsage(
            $this->accountUsageService->used($accountId, self::RESOURCE),
            $this->accountUsageService->limit($accountId, self::RESOURCE),
        );
    }

    /**
     * Hard-block guard: throws when the incoming upload would exceed the cap.
     *
     * @param int $accountId
     * @param float $incomingKb Size of the incoming file, in KB.
     * @return void
     * @throws QuotaExceededException When used + incoming exceeds the limit.
     */
    public function assertCanUpload(int $accountId, float $incomingKb): void
    {
        $used = $this->accountUsageService->used($accountId, self::RESOURCE);
        $limit = $this->accountUsageService->limit($accountId, self::RESOURCE);

        if ($used + max(0.0, $incomingKb) > $limit) {
            $remaining = max(0.0, $limit - $used);

            throw new QuotaExceededException(
                'Storage limit reached (' . $this->formatKb($limit) . '). '
                . 'You have ' . $this->formatKb($remaining) . ' left. '
                . 'Delete some files to free up space.'
            );
        }
    }

    /**
     * Validate the quota, then mint a short-lived presigned PUT URL for R2.
     *
     * @param int $accountId
     * @param string $path R2 object key ({accountId}/{customerId}/filename).
     * @param string $contentType MIME type of the upload.
     * @param int $contentLengthBytes Size of the incoming file, in bytes.
     * @return array{url: string, path: string}
     * @throws QuotaExceededException When the upload would exceed the quota.
     */
    public function createPresignedUpload(int $accountId, string $path, string $contentType, int $contentLengthBytes): array
    {
        $incomingKb = $contentLengthBytes / StorageConstant::BYTES_PER_KB; // bytes -> KB

        $this->assertCanUpload($accountId, $incomingKb);

        // Fail loudly and clearly instead of handing the AWS SDK an empty endpoint,
        // which surfaces as an opaque 500.
        if (!$this->isR2Configured()) {
            throw new \RuntimeException('Cloud storage (R2) is not configured; cannot issue an upload URL.');
        }

        $s3Client = $this->s3Client();

        $cmd = $s3Client->getCommand('PutObject', [
            'Bucket' => $this->r2('bucket'),
            'Key' => $path,
            'ContentType' => $contentType,
        ]);

        $presignedRequest = $s3Client->createPresignedRequest($cmd, '+15 minutes');

        return [
            'url' => (string) $presignedRequest->getUri(),
            'path' => $path,
        ];
    }

    /**
     * Increase the account's live storage counter when a file is recorded.
     *
     * @param int $accountId
     * @param float $kb
     * @return void
     */
    public function incrementUsage(int $accountId, float $kb): void
    {
        $this->accountUsageService->increment($accountId, self::RESOURCE, $kb);
    }

    /**
     * Decrease the account's live storage counter when a file is removed.
     *
     * @param int $accountId
     * @param float $kb
     * @return void
     */
    public function decrementUsage(int $accountId, float $kb): void
    {
        $this->accountUsageService->decrement($accountId, self::RESOURCE, $kb);
    }

    /**
     * Account for a newly uploaded file that replaces an optional previous one:
     * delete the old R2 object (decrementing by its size), then increment by
     * the new file's size. Use for single-file fields (avatars, receipts).
     *
     * @param int $accountId
     * @param string|null $oldPath Previous R2 object path being replaced, if any.
     * @param float $newSizeKb Client-reported size of the new file, in KB.
     * @param string|null $newPath New R2 object path; when given, the true size is
     *                             read from R2 instead of trusting the client value.
     * @return void
     */
    public function registerReplacedFile(int $accountId, ?string $oldPath, float $newSizeKb, ?string $newPath = null): void
    {
        $this->removeFile($accountId, $oldPath);
        $this->incrementUsage($accountId, $this->resolveSizeKb($newPath, $newSizeKb));
    }

    /**
     * Account for a newly uploaded file with no previous version, verifying the
     * size against R2 where possible.
     *
     * @param int $accountId
     * @param string|null $path R2 object path of the upload.
     * @param float $sizeKb Client-reported size, in KB.
     * @return void
     */
    public function registerNewFile(int $accountId, ?string $path, float $sizeKb): void
    {
        $this->incrementUsage($accountId, $this->resolveSizeKb($path, $sizeKb));
    }

    /**
     * Resolve an uploaded object's true size from R2, falling back to the
     * client-reported value when R2 is unconfigured or unreachable. Stops a client
     * from under-reporting a file's size to slip past the quota.
     *
     * @param string|null $path R2 object path.
     * @param float $fallbackKb Client-reported size, in KB.
     * @return float
     */
    public function resolveSizeKb(?string $path, float $fallbackKb): float
    {
        if (!$path || !$this->isR2Configured()) {
            return $fallbackKb;
        }

        try {
            $head = $this->s3Client()->headObject([
                'Bucket' => $this->r2('bucket'),
                'Key' => $path,
            ]);

            return ((int) ($head['ContentLength'] ?? 0)) / StorageConstant::BYTES_PER_KB;
        } catch (\Throwable $e) {
            Log::warning('R2 head failed; using client-reported size', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return $fallbackKb;
        }
    }

    /**
     * Delete an R2 object and release its size from the account's counter.
     * No-op when $path is empty; safe when R2 is unconfigured.
     *
     * @param int $accountId
     * @param string|null $path
     * @return void
     */
    public function removeFile(int $accountId, ?string $path): void
    {
        if (!$path) {
            return;
        }

        $kb = $this->deleteObject($path);

        if ($kb !== null) {
            $this->decrementUsage($accountId, $kb);
        }
    }

    /**
     * Delete an object from R2, returning its size in KB (via HeadObject) so the
     * caller can decrement the counter. Returns null when R2 is not configured
     * or the operation fails.
     *
     * @param string $path
     * @return float|null
     */
    private function deleteObject(string $path): ?float
    {
        if (!$this->isR2Configured()) {
            return null;
        }

        try {
            $s3Client = $this->s3Client();
            $kb = null;

            try {
                $head = $s3Client->headObject(['Bucket' => $this->r2('bucket'), 'Key' => $path]);
                $kb = ((int) ($head['ContentLength'] ?? 0)) / StorageConstant::BYTES_PER_KB;
            } catch (\Throwable $e) {
                // Object may already be gone — still attempt the delete, skip decrement.
            }

            $s3Client->deleteObject(['Bucket' => $this->r2('bucket'), 'Key' => $path]);

            return $kb;
        } catch (\Throwable $e) {
            Log::warning('R2 object delete failed', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Recompute an account's true usage and overwrite the counter.
     * Prefers actual R2 object sizes (authoritative); falls back to the
     * database sum when R2 is unreachable or unconfigured (e.g. local dev).
     *
     * @param Account $account
     * @return StorageUsage
     */
    public function recalculateForAccount(Account $account): StorageUsage
    {
        $usedKb = $this->sumR2UsageKb($account->id);

        // R2 is the only complete source: the DB sum covers tb_customer_files only,
        // while avatars and receipts store just a path (no size column), so it would
        // undercount. Never overwrite a live counter with a figure known to be
        // incomplete — leave it alone and let the next run reconcile.
        if ($usedKb === null) {
            Log::warning('Storage reconcile skipped: R2 unavailable, counter left unchanged', [
                'account_id' => $account->id,
                'db_only_sum_kb' => $this->storageRepository->sumRecordedUsageKb($account->id),
                'note' => 'DB sum excludes avatars/receipts',
            ]);

            return $this->getUsage($account->id);
        }

        $this->accountUsageService->setUsed($account->id, self::RESOURCE, $usedKb);

        return $this->buildUsage($usedKb, $this->accountUsageService->limit($account->id, self::RESOURCE));
    }

    /**
     * Sum the size (KB) of every R2 object under the account prefix.
     *
     * @param int $accountId
     * @return float|null KB used, or null when R2 is not configured or listing fails.
     */
    private function sumR2UsageKb(int $accountId): ?float
    {
        if (!$this->isR2Configured()) {
            return null;
        }

        try {
            $s3Client = $this->s3Client();
            $bytes = 0;
            $continuationToken = null;

            do {
                $params = [
                    'Bucket' => $this->r2('bucket'),
                    'Prefix' => $accountId . '/',
                ];

                if ($continuationToken) {
                    $params['ContinuationToken'] = $continuationToken;
                }

                $result = $s3Client->listObjectsV2($params);

                foreach ($result['Contents'] ?? [] as $object) {
                    // Platform-billing artifacts (subscription/reactivation receipts) live
                    // under the account prefix but never count toward the gym's quota —
                    // skip them so the reconcile matches the live counter.
                    if ($this->isQuotaExempt($accountId, (string) ($object['Key'] ?? ''))) {
                        continue;
                    }

                    $bytes += (int) ($object['Size'] ?? 0);
                }

                $continuationToken = $result['IsTruncated'] ? ($result['NextContinuationToken'] ?? null) : null;
            } while ($continuationToken);

            return $bytes / StorageConstant::BYTES_PER_KB; // bytes -> KB
        } catch (\Throwable $e) {
            Log::warning('R2 usage listing failed; falling back to DB sum', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Whether an R2 object is exempt from the account's storage quota
     * (see StorageConstant::QUOTA_EXCLUDED_PREFIXES).
     *
     * @param int $accountId
     * @param string $key Full R2 object key.
     * @return bool
     */
    public function isQuotaExempt(int $accountId, string $key): bool
    {
        foreach (StorageConstant::QUOTA_EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($key, $accountId . '/' . $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Assemble a StorageUsage snapshot from raw used/limit KB values.
     *
     * @param float $usedKb
     * @param float $limitKb
     * @return StorageUsage
     */
    private function buildUsage(float $usedKb, float $limitKb): StorageUsage
    {
        $usedKb = round($usedKb, 2);
        $limitKb = round($limitKb, 2);
        $remainingKb = round(max(0.0, $limitKb - $usedKb), 2);
        $percent = $limitKb > 0 ? min(100.0, round(($usedKb / $limitKb) * 100, 2)) : 0.0;

        $usage = new StorageUsage();
        $usage->usedKb = $usedKb;
        $usage->limitKb = $limitKb;
        $usage->remainingKb = $remainingKb;
        $usage->usedPercent = $percent;
        $usage->isFull = $usedKb >= $limitKb;
        $usage->isNearLimit = $percent >= StorageConstant::WARNING_THRESHOLD_PERCENT;
        $usage->usedLabel = $this->formatKb($usedKb);
        $usage->limitLabel = $this->formatKb($limitKb);
        $usage->remainingLabel = $this->formatKb($remainingKb);

        return $usage;
    }

    /**
     * Read a value from the `r2` filesystem disk config.
     *
     * Always goes through config() rather than env(): once `config:cache` has run
     * (as it does on boot), env() returns null outside of config files.
     *
     * @param string $key
     * @return string|null
     */
    private function r2(string $key): ?string
    {
        $value = config('filesystems.disks.r2.' . $key);

        return $value !== null ? (string) $value : null;
    }

    /**
     * Whether R2 credentials are present. When false, every R2 operation degrades
     * gracefully instead of throwing SDK errors (e.g. local dev without R2).
     *
     * @return bool
     */
    public function isR2Configured(): bool
    {
        return !empty($this->r2('bucket'))
            && !empty($this->r2('key'))
            && !empty($this->r2('secret'));
    }

    /**
     * Build an R2 (S3-compatible) client from the `r2` disk config.
     *
     * @return S3Client
     */
    private function s3Client(): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => $this->r2('region') ?: 'auto',
            'endpoint' => $this->r2('endpoint'),
            'credentials' => [
                'key' => $this->r2('key'),
                'secret' => $this->r2('secret'),
            ],
            'use_path_style_endpoint' => true,
        ]);
    }

    /**
     * Format a KB value as a human-readable size (KB / MB / GB).
     *
     * @param float $kb
     * @return string
     */
    private function formatKb(float $kb): string
    {
        if ($kb >= StorageConstant::KB_PER_GB) {
            return round($kb / StorageConstant::KB_PER_GB, 2) . ' GB';
        }

        if ($kb >= StorageConstant::KB_PER_MB) {
            return round($kb / StorageConstant::KB_PER_MB, 2) . ' MB';
        }

        return round($kb, 2) . ' KB';
    }
}
