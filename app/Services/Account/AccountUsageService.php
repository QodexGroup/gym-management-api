<?php

namespace App\Services\Account;

use App\Exceptions\QuotaExceededException;
use App\Repositories\Account\AccountUsageRepository;

/**
 * Generic metered-resource service. Any feature that consumes a per-account
 * quota (storage, SMS credits, …) uses this — resource-specific services
 * (e.g. StorageService) wrap it to add their own units/labels.
 */
class AccountUsageService
{
    /**
     * @param AccountUsageRepository $accountUsageRepository
     */
    public function __construct(
        private AccountUsageRepository $accountUsageRepository,
    ) {}

    /**
     * Current consumed amount for a resource, in its base unit.
     *
     * @param int $accountId
     * @param string $resourceKey
     * @return float
     */
    public function used(int $accountId, string $resourceKey): float
    {
        return $this->accountUsageRepository->usedAmount($accountId, $resourceKey);
    }

    /**
     * Effective limit: the per-account override when set, otherwise the
     * config default (config/quotas.php).
     *
     * @param int $accountId
     * @param string $resourceKey
     * @return float
     */
    public function limit(int $accountId, string $resourceKey): float
    {
        $override = $this->accountUsageRepository->limitOverride($accountId, $resourceKey);

        return $override ?? $this->defaultLimit($resourceKey);
    }

    /**
     * Remaining headroom for a resource (never negative), in its base unit.
     *
     * @param int $accountId
     * @param string $resourceKey
     * @return float
     */
    public function remaining(int $accountId, string $resourceKey): float
    {
        return max(0.0, $this->limit($accountId, $resourceKey) - $this->used($accountId, $resourceKey));
    }

    /**
     * Default limit for a resource, read from config/quotas.php.
     *
     * @param string $resourceKey
     * @return float
     */
    public function defaultLimit(string $resourceKey): float
    {
        return (float) config('quotas.defaults.' . $resourceKey, 0);
    }

    /**
     * Guard: throw when consuming $amount would exceed the resource's quota.
     *
     * @param int $accountId
     * @param string $resourceKey
     * @param float $amount
     * @param string|null $message Optional override for the exception message.
     * @return void
     * @throws QuotaExceededException When used + $amount exceeds the limit.
     */
    public function assertCanConsume(int $accountId, string $resourceKey, float $amount, ?string $message = null): void
    {
        $projected = $this->used($accountId, $resourceKey) + max(0.0, $amount);

        if ($projected > $this->limit($accountId, $resourceKey)) {
            throw new QuotaExceededException($message ?? 'Quota reached for ' . $resourceKey . '.');
        }
    }

    /**
     * Increase the live counter for a resource.
     *
     * @param int $accountId
     * @param string $resourceKey
     * @param float $amount
     * @return void
     */
    public function increment(int $accountId, string $resourceKey, float $amount): void
    {
        $this->accountUsageRepository->increment($accountId, $resourceKey, $amount);
    }

    /**
     * Decrease the live counter for a resource (floored at zero).
     *
     * @param int $accountId
     * @param string $resourceKey
     * @param float $amount
     * @return void
     */
    public function decrement(int $accountId, string $resourceKey, float $amount): void
    {
        $this->accountUsageRepository->decrement($accountId, $resourceKey, $amount);
    }

    /**
     * Overwrite the counter with a freshly computed authoritative value.
     *
     * @param int $accountId
     * @param string $resourceKey
     * @param float $amount
     * @return void
     */
    public function setUsed(int $accountId, string $resourceKey, float $amount): void
    {
        $this->accountUsageRepository->setUsed($accountId, $resourceKey, $amount);
    }
}
