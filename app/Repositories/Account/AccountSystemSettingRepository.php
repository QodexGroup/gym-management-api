<?php

namespace App\Repositories\Account;

use App\Models\Account\AccountSystemSetting;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

class AccountSystemSettingRepository extends BaseRepository
{
    /**
     * Get all settings for an account as a flat [set_key => set_value] map.
     *
     * @param int $accountId
     * @return array<string, string|null>
     */
    public function getAllForAccount(int $accountId): array
    {
        return AccountSystemSetting::where('account_id', $accountId)
            ->pluck('set_value', 'set_key')
            ->toArray();
    }

    /**
     * Upsert a batch of key/value settings for an account (single query).
     *
     * @param int $accountId
     * @param array<string, string|null> $keyValues
     * @return void
     */
    public function upsertForAccount(int $accountId, array $keyValues): void
    {
        if (empty($keyValues)) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($keyValues as $key => $value) {
            $rows[] = [
                'account_id' => $accountId,
                'set_key' => $key,
                'set_value' => $value === null ? null : (string) $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('account_system_settings')->upsert($rows, ['account_id', 'set_key'], ['set_value', 'updated_at']);
    }
}
