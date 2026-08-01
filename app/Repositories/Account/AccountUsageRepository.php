<?php

namespace App\Repositories\Account;

use App\Models\Account\AccountUsage;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

class AccountUsageRepository extends BaseRepository
{
    /**
     * Find the usage row for an account + resource, if it exists.
     *
     * @param int $accountId
     * @param string $resourceKey
     * @return AccountUsage|null
     */
    public function find(int $accountId, string $resourceKey): ?AccountUsage
    {
        return AccountUsage::where('account_id', $accountId)
            ->where('resource_key', $resourceKey)
            ->first();
    }

    /**
     * Consumed amount for a resource, or 0 when no row exists yet.
     *
     * @param int $accountId
     * @param string $resourceKey
     * @return float
     */
    public function usedAmount(int $accountId, string $resourceKey): float
    {
        return (float) ($this->find($accountId, $resourceKey)?->used_amount ?? 0);
    }

    /**
     * Per-account limit override for a resource, or null when unset.
     *
     * @param int $accountId
     * @param string $resourceKey
     * @return float|null
     */
    public function limitOverride(int $accountId, string $resourceKey): ?float
    {
        $override = $this->find($accountId, $resourceKey)?->limit_override;

        return $override !== null ? (float) $override : null;
    }

    /**
     * Increase the counter, creating the row on first use. No-op for amount <= 0.
     *
     * @param int $accountId
     * @param string $resourceKey
     * @param float $amount
     * @return void
     */
    public function increment(int $accountId, string $resourceKey, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $row = AccountUsage::firstOrCreate(
            ['account_id' => $accountId, 'resource_key' => $resourceKey],
            ['used_amount' => 0],
        );

        $row->increment('used_amount', $amount);
    }

    /**
     * Decrease the counter, never below zero. No-op for amount <= 0.
     *
     * @param int $accountId
     * @param string $resourceKey
     * @param float $amount
     * @return void
     */
    public function decrement(int $accountId, string $resourceKey, float $amount): void
    {
        $amount = max(0.0, $amount);
        if ($amount <= 0) {
            return;
        }

        // CASE/WHEN instead of GREATEST(): stays atomic while remaining portable
        // across MySQL and SQLite (GREATEST is MySQL-only).
        $delta = sprintf('%.2f', $amount);

        AccountUsage::where('account_id', $accountId)
            ->where('resource_key', $resourceKey)
            ->update([
                'used_amount' => DB::raw(
                    'CASE WHEN used_amount - ' . $delta . ' < 0 THEN 0 ELSE used_amount - ' . $delta . ' END'
                ),
            ]);
    }

    /**
     * Set the counter to an exact value (creating the row if needed), floored at zero.
     *
     * @param int $accountId
     * @param string $resourceKey
     * @param float $amount
     * @return void
     */
    public function setUsed(int $accountId, string $resourceKey, float $amount): void
    {
        AccountUsage::updateOrCreate(
            ['account_id' => $accountId, 'resource_key' => $resourceKey],
            ['used_amount' => max(0.0, $amount)],
        );
    }
}
