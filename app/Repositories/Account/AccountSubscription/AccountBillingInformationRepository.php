<?php

namespace App\Repositories\Account\AccountSubscription;

use App\Models\Account\AccountBillingInformation;

class AccountBillingInformationRepository
{
    public function findByAccountId(int $accountId): ?AccountBillingInformation
    {
        return AccountBillingInformation::where('account_id', $accountId)->first();
    }

    public function updateOrCreateForAccount(int $accountId, array $data): AccountBillingInformation
    {
        return AccountBillingInformation::updateOrCreate(
            ['account_id' => $accountId],
            $data
        );
    }
}
