<?php

namespace App\Services\Core;

use App\Constant\CustomerBillConstant;
use App\Helpers\GenericData;
use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use App\Models\Account\MembershipPlan;
use App\Models\Core\CustomerBill;
use App\Repositories\Account\MembershipPlanRepository;
use App\Repositories\Core\CustomerRepository;
use App\Repositories\Core\CustomerBillRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerService
{
    public function __construct(
        private CustomerRepository $repository,
        private MembershipPlanRepository $membershipPlanRepository,
        private CustomerBillRepository $customerBillRepository
    ) {
    }

    /**
     * Create a new customer with membership and trainer assignment
     *
     * @param GenericData $genericData
     * @return Customer
     */
    public function create(GenericData $genericData): Customer
    {
        try {
            return DB::transaction(function () use ($genericData) {
                $data = $genericData->getData();

                // Extract membership plan ID and trainer ID
                $membershipPlanId = $data->membershipPlanId ?? null;
                $currentTrainerId = $data->currentTrainerId ?? null;

                // Remove these from data as they're not direct customer fields
                unset($genericData->data['membershipPlanId'], $genericData->data['currentTrainerId']);

                // Calculate balance from membership plan if provided
                if ($membershipPlanId) {
                    $plan = $this->membershipPlanRepository->findMembershipPlanById($membershipPlanId, $genericData->userData->account_id);
                    $genericData->getData()->balance = $plan->price;
                } else {
                    // Set default balance if no membership plan
                    $genericData->getData()->balance = 0;
                }

                $genericData->syncDataArray();

                // Create customer
                $customer = $this->repository->create($genericData);

                // Create membership if plan is selected
                if ($membershipPlanId) {
                    $this->createMembership($customer->id, $membershipPlanId, $genericData->userData->account_id);
                    // create bill for the membership plan
                    $this->createBillFromCustomerMembership($customer->id, $membershipPlanId, $genericData);
                }

                // Attach trainer if provided
                if ($currentTrainerId) {
                    $customer->trainers()->sync([$currentTrainerId]);
                }

                return $customer->fresh(['currentTrainer']);
            });
        } catch (\Throwable $th) {
            Log::error('Error creating customer', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            throw $th;
        }
    }

    /**
     * Update a customer (membership and trainer are not updated)
     *
     * @param int $id
     * @param GenericData $genericData
     * @return Customer
     */
    public function update(int $id, GenericData $genericData): Customer
    {
        // Remove membership and trainer fields if they exist (shouldn't be sent from frontend)
        unset($genericData->data['membershipPlanId'], $genericData->data['currentTrainerId']);

        // Don't update balance when editing - keep existing balance
        unset($genericData->data['balance']);

        // Update customer
        $customer = $this->repository->update($id, $genericData);

        // Return fresh customer with relationships loaded
        return $customer->fresh(['currentMembership.membershipPlan', 'currentTrainer']);
    }

    /**
     * Create or update a customer's membership.
     *
     * @param int $customerId
     * @param GenericData $genericData
     * @return CustomerMembership
     */
    public function createOrUpdateMembership(int $customerId, GenericData $genericData): CustomerMembership
    {
        try {
            $accountId = $genericData->userData->account_id;
            $data = $genericData->getData();

            return DB::transaction(function () use ($accountId, $customerId, $genericData, $data) {
                /** @var MembershipPlan $membershipPlan */
                $membershipPlan = $this->membershipPlanRepository->findMembershipPlanById($data->membershipPlanId, $accountId);

                $startDate = Carbon::parse($data->membershipStartDate ?? Carbon::now());

                $membership = $this->repository->createMembership($accountId, $customerId, $membershipPlan, $startDate);
                $membership->load('membershipPlan');

                // Reuse GenericData for bill creation (update data property)
                // Convert object back to array for data property
                $genericData->data = json_decode(json_encode($data), true);
                $this->createBillFromCustomerMembership($customerId, $membershipPlan->id, $genericData);

                return $membership;
            });
        } catch (\Throwable $th) {
            Log::error('Error creating/updating customer membership', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            throw $th;
        }
    }

    /**
     * Create a membership for a customer
     *
     * @param int $customerId
     * @param int $membershipPlanId
     * @param int $accountId
     * @return CustomerMembership
     */
    private function createMembership(int $customerId, int $membershipPlanId, int $accountId): CustomerMembership
    {
        $plan = $this->membershipPlanRepository->findMembershipPlanById($membershipPlanId, $accountId);
        return $this->repository->createMembership($accountId, $customerId, $plan);
    }

    /**
     * @param int $customerId
     * @param int $membershipPlanId
     * @param GenericData $genericData
     *
     * @return CustomerBill
     */
    private function createBillFromCustomerMembership(int $customerId, int $membershipPlanId, GenericData $genericData): CustomerBill
    {
        $accountId = $genericData->userData->account_id;
        $plan = $this->membershipPlanRepository->findMembershipPlanById($membershipPlanId, $accountId);
        if (!$plan) {
            throw new \Exception('Membership plan not found');
        }

        $data = $genericData->getData();

        // Update the existing GenericData with bill data
        $genericData->data = [
            'customerId' => $customerId,
            'grossAmount' => $plan->price,
            'discountPercentage' => 0,
            'netAmount' => $plan->price,
            'paidAmount' => 0,
            'billDate' => Carbon::now(),
            'billStatus' => CustomerBillConstant::BILL_STATUS_ACTIVE,
            'billType' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'membershipPlanId' => $membershipPlanId,
            'createdBy' => $data->createdBy ?? $genericData->userData->id,
            'updatedBy' => $data->updatedBy ?? $genericData->userData->id,
        ];

        $bill = $this->customerBillRepository->create($genericData);
        return $bill;
    }

}

