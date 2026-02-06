<?php

namespace App\Repositories\Core;

use App\Constant\CustomerPtPackageConstant;
use App\Helpers\GenericData;
use App\Models\Core\CustomerPtPackage;
use Illuminate\Database\Eloquent\Collection;

class CustomerPtPackageRepository
{

    /**
     * Find a customer PT package by ID
     *
     * @param int $customerPtPackageId
     * @param int $accountId
     * @return CustomerPtPackage
     */
    public function findCustomerPtPackageById(int $customerPtPackageId, int $accountId): CustomerPtPackage
    {
        return CustomerPtPackage::where('id', $customerPtPackageId)
            ->where('account_id', $accountId)
            ->firstOrFail();
    }

    /**
     * @param int $customerId
     * @param GenericData $genericData
     *
     * @return Collection
     */
    public function getPtPackages(int $customerId, GenericData $genericData): Collection
    {
        $customerPtPackages = CustomerPtPackage::where('customer_id', $customerId)
            ->where('account_id', $genericData->userData->account_id)
            ->where('status', CustomerPtPackageConstant::STATUS_ACTIVE);

        $genericData->applyRelations($customerPtPackages);

        return $customerPtPackages->get();
    }
    /**
     * @param int $customerId
     * @param GenericData $genericData
     *
     * @return CustomerPtPackage
     */
    public function createPtPackage(int $customerId, GenericData $genericData): CustomerPtPackage
    {
        $genericData->getData()->account_id = $genericData->userData->account_id;
        $genericData->getData()->customer_id = $customerId;
        $genericData->getData()->status = CustomerPtPackageConstant::STATUS_ACTIVE;
        $genericData->getData()->created_by = $genericData->userData->id;
        $genericData->getData()->updated_by = $genericData->userData->id;
        $genericData->syncDataArray();

        return CustomerPtPackage::create($genericData->data)->fresh();
    }

    /**
     * Cancel/remove PT package by setting status to inactive
     *
     * @param int $ptPackageId
     * @param GenericData $genericData
     *
     * @return CustomerPtPackage
     */
    public function removePtPackage(int $ptPackageId, GenericData $genericData): CustomerPtPackage
    {
        $customerPtPackage = CustomerPtPackage::where('id', $ptPackageId)
            ->where('customer_id', $genericData->getData()->customerId)
            ->where('account_id', $genericData->userData->account_id)
            ->firstOrFail();

        $customerPtPackage->status = CustomerPtPackageConstant::STATUS_INACTIVE;
        $customerPtPackage->updated_by = $genericData->userData->id;

        $customerPtPackage->save();
        return $customerPtPackage->fresh();
    }

    /**
     * @param int $ptPackageId
     * @param array $data
     *
     * @return int
     */
    public function updateCustomerPtPackage(int $ptPackageId, array $data): int
    {
        return CustomerPtPackage::where('id', $ptPackageId)->update($data);
    }

    /**
     * Update the number of sessions of a customer PT package
     *
     * @param int $customerId
     * @param int $ptPackageId
     * @param GenericData $genericData
     *
     * @return int
     */
    public function updateCustomerPtPackageSessions(int $customerId, int $ptPackageId, GenericData $genericData): int
    {
        return CustomerPtPackage::where('id', $ptPackageId)
            ->where('customer_id', $customerId)
            ->where('account_id', $genericData->userData->account_id)
            ->decrement('number_of_sessions_remaining', 1);

    }
}
