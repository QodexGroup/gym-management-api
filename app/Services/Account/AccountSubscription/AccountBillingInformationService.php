<?php

namespace App\Services\Account\AccountSubscription;

use App\Models\Account;

class AccountBillingInformationService
{
    public function getByAccountId(int $accountId): ?Account
    {
        return Account::find($accountId);
    }

    public function updateOrCreate(int $accountId, array $data): Account
    {
        $allowed = [
            'legal_name', 'billing_email',
            'address_line_1', 'address_line_2', 'city', 'state_province', 'postal_code', 'country',
        ];
        $filtered = array_intersect_key($data, array_flip($allowed));
        $account = Account::findOrFail($accountId);
        $account->update($filtered);

        return $account->fresh();
    }
}
