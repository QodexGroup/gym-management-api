<?php

namespace App\Repositories\Core;

use App\Constant\CustomerPtPackageConstant;
use App\Helpers\GenericData;
use App\Models\Core\CustomerPtPackage;
use Illuminate\Database\Eloquent\Collection;

class CustomerPtPackageRepository
{

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
}
