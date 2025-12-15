<?php

namespace App\Services\Core;

use App\Constant\CustomerBillConstant;
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
     * @param array $data
     * @return Customer
     */
    public function create(array $data): Customer
    {
        try {
            return DB::transaction(function () use ($data) {
                // Extract membership plan ID and trainer ID
                $membershipPlanId = $data['membershipPlanId'] ?? null;
                $currentTrainerId = $data['currentTrainerId'] ?? null;

                // Remove these from data array as they're not direct customer fields
                unset($data['membershipPlanId'], $data['currentTrainerId']);

                // Set account_id to 1 by default
                $data['account_id'] = 1;

                // Calculate balance from membership plan if provided
                if ($membershipPlanId) {
                    $plan = $this->membershipPlanRepository->getById($membershipPlanId);
                    $data['balance'] = $plan->price;
                } else {
                    // Set default balance if no membership plan
                    $data['balance'] = 0;
                }

                // Create customer
                $customer = $this->repository->create($data);

                // Create membership if plan is selected
                if ($membershipPlanId) {
                    $this->createMembership($customer->id, $membershipPlanId, $data['account_id']);
                    // create bill for the membership plan
                    $this->createBillFromCustomerMembership($customer->id, $membershipPlanId, $data);
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
     * @param array $data
     * @return Customer
     */
    public function update(int $id, array $data): Customer
    {
        // Remove membership and trainer fields if they exist (shouldn't be sent from frontend)
        unset($data['membershipPlanId'], $data['currentTrainerId']);

        // Don't update balance when editing - keep existing balance
        unset($data['balance']);

        // Update customer
        $this->repository->update($id, $data);

        // Return fresh customer with relationships loaded
        return $this->repository->getById($id);
    }

    /**
     * Create or update a customer's membership.
     *
     * @param int $customerId
     * @param array $data
     * @return CustomerMembership
     */
    public function createOrUpdateMembership(int $customerId, array $data): CustomerMembership
    {
        try {
            $accountId = 1;

            return DB::transaction(function () use ($accountId, $customerId, $data) {
                /** @var MembershipPlan $membershipPlan */
                $membershipPlan = $this->membershipPlanRepository->getById($data['membershipPlanId']);

                $startDate = Carbon::parse($data['membershipStartDate']);

                $membership = $this->repository->createMembership($accountId, $customerId, $membershipPlan, $startDate);
                $membership->load('membershipPlan');
                $this->createBillFromCustomerMembership($customerId, $membership->id, $data);

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
        $plan = $this->membershipPlanRepository->getById($membershipPlanId);
        return $this->repository->createMembership($accountId, $customerId, $plan);
    }

    /**
     * @param int $customerId
     * @param int $membershipPlanId
     * @param array $data
     *
     * @return CustomerBill
     */
    private function createBillFromCustomerMembership(int $customerId, int $membershipPlanId, array $data): CustomerBill
    {
        $plan = $this->membershipPlanRepository->getById($membershipPlanId);
        if (!$plan) {
            throw new \Exception('Membership plan not found');
        }
        $data['customer_id'] = $customerId;
        $data['gross_amount'] = $plan->price;
        $data['discount_percentage'] = 0;
        $data['net_amount'] = $plan->price;
        $data['paid_amount'] = 0;
        $data['bill_date'] = Carbon::now();
        $data['bill_status'] = CustomerBillConstant::BILL_STATUS_ACTIVE;
        $data['bill_type'] = CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION;
        $data['membership_plan_id'] = $membershipPlanId;
        $data['created_by'] = $data['createdBy'] ?? 1;
        $data['updated_by'] = $data['updatedBy'] ?? 1;

        $bill = $this->customerBillRepository->create($data);
        return $bill;
    }

}

