<?php

namespace App\Repositories\Core;

use App\Constant\AccountStatusConstant;
use App\Models\Account\Account;
use App\Models\Core\CustomerFiles;
use App\Repositories\BaseRepository;

class StorageRepository extends BaseRepository
{
    /**
     * Fetch an account by id.
     *
     * @param int $accountId
     * @return Account|null
     */
    public function findAccountById(int $accountId): ?Account
    {
        return Account::find($accountId);
    }

    /**
     * Authoritative usage from the database: the sum of recorded, non-deleted
     * file sizes (KB) for the account. Used as the R2 reconcile fallback.
     *
     * @param int $accountId
     * @return float
     */
    public function sumRecordedUsageKb(int $accountId): float
    {
        return (float) CustomerFiles::where('account_id', $accountId)->sum('file_size');
    }

    /**
     * Ids of all active accounts, for the daily reconcile job.
     *
     * @return array<int, int>
     */
    public function activeAccountIds(): array
    {
        return Account::where('status', AccountStatusConstant::STATUS_ACTIVE)
            ->pluck('id')
            ->all();
    }
}
