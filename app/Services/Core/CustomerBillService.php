<?php

namespace App\Services\Core;

use App\Constant\CustomerBillConstant;
use App\Constant\CustomerMembershipConstant;
use App\Helpers\GenericData;
use App\Repositories\Account\MembershipPlanRepository;
use App\Repositories\Core\CustomerBillRepository;
use App\Repositories\Core\CustomerRepository;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerBillService
{
    public function __construct(
        private CustomerBillRepository $customerBillRepository,
        private CustomerRepository $customerRepository,
        private MembershipPlanRepository $membershipPlanRepository
    ) {
    }

    /**
     * @param GenericData $genericData
     *
     * @return CustomerBill
     */
    public function create(GenericData $genericData): CustomerBill
    {
        try {
            return DB::transaction(function () use ($genericData) {
                $data = $genericData->getData();
                $customerId = $data->customerId;
                $accountId = $genericData->userData->account_id;
                $bill = null;

                if($data->billType == CustomerBillConstant::BILL_TYPE_CUSTOM_AMOUNT) {
                    $bill = $this->customerBillRepository->create($genericData);
                }
                else{
                    // this is for membership subscription
                    $membershipPlanId = $data->membershipPlanId ?? null;
                    // set customer membership plan id
                    if ($membershipPlanId) {
                        $membershipPlan = $this->membershipPlanRepository->findMembershipPlanById($membershipPlanId, $accountId);
                        $this->customerRepository->createMembership($accountId, $customerId, $membershipPlan);
                    }

                    $bill = $this->customerBillRepository->create($genericData);
                }
                $bill->refresh();

                // Recalculate and update customer balance
                $customer = $this->customerRepository->findCustomerById($customerId, $accountId);
                $customer->recalculateBalance();

                // Refresh customer to load updated relationships
                $customer->refresh();
                $customer->load(['currentMembership.membershipPlan', 'currentTrainer']);

                return $bill;
            });
        } catch (\Throwable $th) {
            Log::error('Error creating customer bill', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            throw $th;
        }
    }


    /**
     * @param int $id
     * @param GenericData $genericData
     *
     * @return CustomerBill
     */
    public function updateBill(int $id, GenericData $genericData): CustomerBill
    {
        try {
            return DB::transaction(function () use ($genericData, $id) {
                $data = $genericData->getData();
                $customerId = $data->customerId;
                $accountId = $genericData->userData->account_id;

                // Get the existing bill to check for membership plan changes
                $existingBill = $this->customerBillRepository->findBillById($id, $accountId);
                $oldMembershipPlanId = $existingBill->membership_plan_id;
                $newMembershipPlanId = $data->membershipPlanId ?? null;

                // Handle membership subscription bill type
                if($data->billType == CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION) {
                    // Check if membership plan changed
                    if ($newMembershipPlanId && $oldMembershipPlanId != $newMembershipPlanId) {
                        // Remove old membership if it exists
                        if ($oldMembershipPlanId) {
                            CustomerMembership::where('customer_id', $customerId)
                                ->where('membership_plan_id', $oldMembershipPlanId)
                                ->where('status', CustomerMembershipConstant::STATUS_ACTIVE)
                                ->update(['status' => CustomerMembershipConstant::STATUS_DEACTIVATED]);
                        }

                        // Create new membership
                        $membershipPlan = $this->membershipPlanRepository->findMembershipPlanById($newMembershipPlanId, $accountId);
                        $this->customerRepository->createMembership($accountId, $customerId, $membershipPlan);
                    }
                }

                // Update the bill
                $bill = $this->customerBillRepository->update($id, $genericData);
                $bill->refresh();

                // Recalculate and update customer balance
                $customer = $this->customerRepository->findCustomerById($customerId, $accountId);
                $customer->recalculateBalance();

                // Refresh customer to load updated relationships
                $customer->refresh();
                $customer->load(['currentMembership.membershipPlan', 'currentTrainer']);

                return $bill;
            });
        } catch (\Throwable $th) {
            Log::error('Error updating customer bill', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            throw $th;
        }
    }

    /**
     * @param int $id
     * @param int $accountId
     *
     * @return bool
     */
    public function deleteBill(int $id, int $accountId): bool
    {
        try {
            return DB::transaction(function () use ($id, $accountId) {
                $bill = $this->customerBillRepository->findBillById($id, $accountId);
                // Do not allow deleting fully paid bills
                if ($bill->bill_status === CustomerBillConstant::BILL_STATUS_PAID) {
                    throw new \RuntimeException('Cannot delete a fully paid bill. Please delete payments instead.');
                }
                $customerId = $bill->customer_id;
                $this->customerBillRepository->delete($id, $accountId);
                $customer = $this->customerRepository->findCustomerById($customerId, $accountId);
                $customer->recalculateBalance();

                // Refresh customer to load updated relationships
                $customer->refresh();
                $customer->load(['currentMembership.membershipPlan', 'currentTrainer']);

                return true;
            });
        } catch (\Throwable $th) {
            Log::error('Error deleting customer bill', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            throw $th;
        }
    }

}
