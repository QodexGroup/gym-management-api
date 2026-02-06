<?php

namespace App\Services\Core;

use App\Helpers\GenericData;
use App\Repositories\Core\CustomerBillRepository;
use App\Repositories\Core\CustomerRepository;
use App\Repositories\Core\CustomerPtPackageRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerPtPackageService
{
    public function __construct(
        private CustomerPtPackageRepository $customerPtPackageRepository,
        private CustomerBillRepository $customerBillRepository,
        private CustomerRepository $customerRepository,
    ) {
    }

    /**
     * @param int $ptPackageId
     * @param GenericData $genericData
     *
     * @return bool
     */
    public function removePtPackage(int $ptPackageId, GenericData $genericData): bool
    {
        try {
            return DB::transaction(function () use ( $ptPackageId, $genericData) {

                $accountId = $genericData->userData->account_id;
                $customerId = $genericData->getData()->customerId;
                $customer = $this->customerRepository->findCustomerById($customerId, $accountId);
                $customerPtPackage = $this->customerPtPackageRepository->removePtPackage($ptPackageId, $genericData);
                if (!$customerPtPackage) {
                    throw new \Exception('Failed to delete PT package');
                }
                // Void the bill
                $voided = $this->customerBillRepository->voidBill($customerPtPackage->bill_id, $accountId);

                if (!$voided) {
                    throw new \Exception('Failed to void bill');
                }
                // Recalculate customer balance
                $customer->refresh();
                $customer->recalculateBalance();

                return true;
            });
        } catch (\Throwable $th) {
            Log::error('Error removing PT package', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            return false;
        }
    }
}
