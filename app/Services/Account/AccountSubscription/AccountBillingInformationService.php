<?php

namespace App\Services\Account\AccountSubscription;

use App\Models\Account\AccountBillingInformation;
use App\Repositories\Account\AccountSubscription\AccountBillingInformationRepository;

class AccountBillingInformationService
{
    public function __construct(
        private AccountBillingInformationRepository $billingRepository
    ) {
    }

    public function getByAccountId(int $accountId): ?AccountBillingInformation
    {
        return $this->billingRepository->findByAccountId($accountId);
    }

    public function updateOrCreate(int $accountId, array $data): AccountBillingInformation
    {
        $allowed = [
            'legal_name', 'business_name', 'billing_email', 'tax_id', 'vat_number',
            'address_line_1', 'address_line_2', 'city', 'state_province', 'postal_code', 'country',
        ];
        $filtered = array_intersect_key($data, array_flip($allowed));
        return $this->billingRepository->updateOrCreateForAccount($accountId, $filtered);
    }
}
