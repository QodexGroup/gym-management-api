<?php

namespace App\Services\Core;

use App\Constant\CustomerBillConstant;
use App\Constant\CustomerMembershipConstant;
use App\Helpers\GenericData;
use App\Models\Core\Customer;
use App\Services\Account\AccountLimitService;
use App\Models\Core\CustomerMembership;
use App\Models\Account\MembershipPlan;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerPtPackage;
use App\Repositories\Account\MembershipPlanRepository;
use App\Repositories\Account\PtPackageRepository;
use App\Repositories\Core\CustomerRepository;
use App\Repositories\Core\CustomerBillRepository;
use App\Repositories\Core\CustomerPtPackageRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerService
{
    public function __construct(
        private CustomerRepository $repository,
        private MembershipPlanRepository $membershipPlanRepository,
        private CustomerBillRepository $customerBillRepository,
        private NotificationService $notificationService,
        private PtPackageRepository $ptPackageRepository,
        private CustomerPtPackageRepository $customerPtPackageRepository,
        private AccountLimitService $accountLimitService,
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
        $check = $this->accountLimitService->canCreate($genericData->userData->account_id, AccountLimitService::RESOURCE_CUSTOMERS);
        if (!$check['allowed']) {
            throw new \Exception($check['message'] ?? 'Limit reached');
        }

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

                // Send customer registration notification
                $this->notificationService->createCustomerRegisteredNotification($customer);

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
                // Get current active membership before creating new one
                $customer = $this->repository->findCustomerById($customerId, $accountId);
                $oldMembership = $customer->currentMembership;

                /** @var MembershipPlan $membershipPlan */
                $membershipPlan = $this->membershipPlanRepository->findMembershipPlanById($data->membershipPlanId, $accountId);

                $startDate = Carbon::parse($data->membershipStartDate ?? Carbon::now());

                // Create new membership (this will deactivate old active memberships)
                $membership = $this->repository->createMembership($accountId, $customerId, $membershipPlan, $startDate);
                $membership->load('membershipPlan');

                // If there was an old membership, void its bills with outstanding balance
                if ($oldMembership) {
                    $oldBills = $this->customerBillRepository->findMembershipBillsWithOutstandingBalance(
                        $customerId,
                        $accountId,
                        $oldMembership->membership_plan_id
                    );

                    foreach ($oldBills as $oldBill) {
                        $this->customerBillRepository->voidBill($oldBill->id, $accountId);
                    }
                }

                // Create automated bill for the new membership period (bill date = membership start date)
                $this->customerBillRepository->createAutomatedBill(
                    $accountId,
                    $customerId,
                    $membershipPlan->id,
                    $membershipPlan->price,
                    $startDate
                );

                // Recalculate customer balance
                $customer->refresh();
                $customer->recalculateBalance();

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
     * Create or update a customer's PT package.
     *
     * @param int $customerId
     * @param GenericData $genericData
     * @return CustomerPtPackage
     */
    public function createPtPackage(int $customerId, GenericData $genericData): CustomerPtPackage
    {
        try {

            return DB::transaction(function () use ( $customerId, $genericData) {

                $data = $genericData->getData();
                $accountId = $genericData->userData->account_id;

                $customer = $this->repository->findCustomerById($customerId, $accountId);
                // find selected pt package
                $ptPackage = $this->ptPackageRepository->findPtPackageById($data->ptPackageId, $accountId);
                // additional data for bill and customer pt package
                $genericData->getData()->grossAmount = $ptPackage->price;
                $genericData->getData()->netAmount = $ptPackage->price;
                $genericData->getData()->numberOfSessionsRemaining = $ptPackage->number_of_sessions;

                $customerPtPackage = $this->customerPtPackageRepository->createPtPackage($customerId, $genericData);
                $customerPtPackage->load('ptPackage');

                // Create automated bill for the new PT package
                $bill = $this->customerBillRepository->createAutomatedBillForPtPackage($genericData);
                $updateData = [
                    'bill_id' => $bill->id,
                ];

                $this->customerPtPackageRepository->updateCustomerPtPackage($customerPtPackage->id, $updateData);


                // Recalculate customer balance
                $customer->refresh();
                $customer->recalculateBalance();

                return $customerPtPackage;
            });
        } catch (\Throwable $th) {
            Log::error('Error creating/updating customer PT package', [
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

