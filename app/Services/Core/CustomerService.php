<?php

namespace App\Services\Core;

use App\Constant\CustomerBillConstant;
use App\Constant\CustomerMembershipConstant;
use App\Constant\MembershipSettingConstant;
use App\Helpers\GenericData;
use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use App\Models\Account\MembershipPlan;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerPtPackage;
use App\Repositories\Account\MembershipPlanRepository;
use App\Repositories\Account\PtPackageRepository;
use App\Repositories\Core\CustomerRepository;
use App\Repositories\Core\CustomerBillRepository;
use App\Repositories\Core\CustomerPtPackageRepository;
use App\Services\Account\AccountSystemSettingService;
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
        private StorageService $storageService,
        private AccountSystemSettingService $membershipSettingService,
    ) {
    }

    /**
     * Set a customer's photo, replacing any previous one and counting storage.
     *
     * @param int $id
     * @param int $accountId
     * @param string $path R2 object path of the uploaded photo.
     * @param float $sizeKb Size of the uploaded photo, in KB.
     * @return Customer
     */
    public function updatePhoto(int $id, int $accountId, string $path, float $sizeKb): Customer
    {
        $oldPhoto = $this->repository->findCustomerById($id, $accountId)->photo;
        $customer = $this->repository->setPhoto($id, $accountId, $path);

        if ($path !== $oldPhoto) {
            $this->storageService->registerReplacedFile($accountId, $oldPhoto, $sizeKb, $path);
        }

        return $customer;
    }

    /**
     * Remove a customer's photo and release its storage.
     *
     * @param int $id
     * @param int $accountId
     * @return Customer
     */
    public function removePhoto(int $id, int $accountId): Customer
    {
        $oldPhoto = $this->repository->findCustomerById($id, $accountId)->photo;
        $customer = $this->repository->setPhoto($id, $accountId, null);

        $this->storageService->removeFile($accountId, $oldPhoto);

        return $customer;
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

                /** @var MembershipPlan $newPlan */
                $newPlan = $this->membershipPlanRepository->findMembershipPlanById($data->membershipPlanId, $accountId);
                $startDate = Carbon::parse($data->membershipStartDate ?? Carbon::now());

                // A genuine mid-cycle plan change on an active, unexpired membership is
                // governed by the account's plan-change mode. Everything else (new member,
                // expired membership, or re-selecting the same plan) is a full assignment.
                if ($this->isMidCyclePlanChange($oldMembership, $newPlan)) {
                    $mode = $this->membershipSettingService->get($accountId, 'planChangeMode');

                    if ($mode === MembershipSettingConstant::PLAN_CHANGE_NEXT_RENEWAL) {
                        return $this->schedulePlanChangeAtRenewal($oldMembership, $newPlan);
                    }

                    if ($mode === MembershipSettingConstant::PLAN_CHANGE_IMMEDIATE_PRORATION) {
                        return $this->applyImmediateProration($customer, $oldMembership, $newPlan, $genericData);
                    }
                }

                // Manually renewing/re-billing a member who ALREADY has an active membership
                // is a "manual membership bill" and is gated by the account setting. New
                // members and lapsed/expired members (onboarding and reactivation) are
                // always allowed so staff can get them active.
                if ($this->hasActiveMembership($oldMembership)
                    && !$this->membershipSettingService->get($accountId, 'allowManualMembershipBills')) {
                    throw new \RuntimeException('Manual membership billing is disabled for this account.');
                }

                return $this->assignMembershipFullSwitch($customer, $oldMembership, $newPlan, $startDate);
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
                $genericData->getData()->packageName = $ptPackage->package_name;
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
     * Void all outstanding bills tied to a membership plan before assigning a new one.
     */
    private function voidOutstandingMembershipBills(CustomerMembership $oldMembership, int $customerId, int $accountId): void
    {
        $oldBills = $this->customerBillRepository->findMembershipBillsWithOutstandingBalance(
            $customerId,
            $accountId,
            $oldMembership->membership_plan_id
        );

        foreach ($oldBills as $oldBill) {
            $this->customerBillRepository->voidBill($oldBill->id, $accountId);
        }
    }

    /**
     * A mid-cycle plan change is switching an active, not-yet-expired membership
     * to a different plan. New members, expired memberships, or re-selecting the
     * same plan are handled as a normal (full) assignment instead.
     *
     * @param CustomerMembership|null $oldMembership
     * @param MembershipPlan $newPlan
     * @return bool
     */
    private function isMidCyclePlanChange(?CustomerMembership $oldMembership, MembershipPlan $newPlan): bool
    {
        if (!$this->hasActiveMembership($oldMembership)) {
            return false;
        }

        return (int) $oldMembership->membership_plan_id !== (int) $newPlan->id;
    }

    /**
     * Whether the membership is active and still within its paid period (not expired).
     *
     * @param CustomerMembership|null $membership
     * @return bool
     */
    private function hasActiveMembership(?CustomerMembership $membership): bool
    {
        return $membership
            && $membership->status === CustomerMembershipConstant::STATUS_ACTIVE
            && Carbon::parse($membership->membership_end_date)->startOfDay()->greaterThanOrEqualTo(Carbon::today());
    }

    /**
     * Schedule the plan change to take effect at the next renewal. The member keeps
     * their current plan and paid period; no bill is created now.
     *
     * @param CustomerMembership $oldMembership
     * @param MembershipPlan $newPlan
     * @return CustomerMembership
     */
    private function schedulePlanChangeAtRenewal(CustomerMembership $oldMembership, MembershipPlan $newPlan): CustomerMembership
    {
        $membership = $this->repository->setPendingPlan($oldMembership->id, $newPlan->id);
        $membership->load(['membershipPlan', 'pendingPlan']);

        Log::info('Plan change scheduled for next renewal', [
            'membership_id' => $oldMembership->id,
            'from_plan_id' => $oldMembership->membership_plan_id,
            'to_plan_id' => $newPlan->id,
        ]);

        return $membership;
    }

    /**
     * Apply a plan change immediately, prorated over the remaining days of the
     * current cycle. Upgrades raise a prorated adjustment bill; downgrades settle
     * the leftover value per the account's downgrade credit mode.
     *
     * @param Customer $customer
     * @param CustomerMembership $oldMembership
     * @param MembershipPlan $newPlan
     * @param GenericData $genericData
     * @return CustomerMembership
     */
    private function applyImmediateProration(Customer $customer, CustomerMembership $oldMembership, MembershipPlan $newPlan, GenericData $genericData): CustomerMembership
    {
        $accountId = $genericData->userData->account_id;
        $oldMembership->loadMissing('membershipPlan');
        $oldPlan = $oldMembership->membershipPlan;

        $today = Carbon::today();
        $start = Carbon::parse($oldMembership->membership_start_date)->startOfDay();
        $end = Carbon::parse($oldMembership->membership_end_date)->startOfDay();

        $totalDays = max(1, $start->diffInDays($end) + 1);
        $remainingDays = max(0, $today->diffInDays($end) + 1);
        $fraction = min(1.0, $remainingDays / $totalDays);

        $oldPrice = (float) $oldPlan->price;
        $newPrice = (float) $newPlan->price;
        $diff = round(($newPrice - $oldPrice) * $fraction, 2);

        $newEndDate = $end;

        if ($diff > 0) {
            // Upgrade: charge the prorated difference for the remaining days.
            $description = "Plan upgrade: {$oldPlan->plan_name} → {$newPlan->plan_name} (prorated {$remainingDays} day/s)";
            $this->createPlanChangeAdjustmentBill($customer->id, $diff, $description, $genericData);
        } elseif ($diff < 0) {
            // Downgrade: settle the leftover value per the configured mode.
            $downgradeMode = $this->membershipSettingService->get($accountId, 'downgradeCreditMode');
            if ($downgradeMode === MembershipSettingConstant::DOWNGRADE_EXTEND_DAYS) {
                $newEndDate = $this->extendEndDateForDowngradeCredit($end, $today, $newPlan, -$diff);
            }
        }

        $membership = $this->repository->changePlanImmediately($oldMembership->id, $newPlan->id, $newEndDate);
        $membership->load(['membershipPlan', 'pendingPlan']);

        $customer->refresh();
        $customer->recalculateBalance();

        Log::info('Plan change applied immediately (prorated)', [
            'membership_id' => $oldMembership->id,
            'from_plan_id' => $oldMembership->getOriginal('membership_plan_id'),
            'to_plan_id' => $newPlan->id,
            'remaining_days' => $remainingDays,
            'proration_diff' => $diff,
        ]);

        return $membership;
    }

    /**
     * Convert leftover downgrade value into extra days on the new (cheaper) plan.
     *
     * @param Carbon $currentEnd
     * @param Carbon $today
     * @param MembershipPlan $newPlan
     * @param float $credit
     * @return Carbon
     */
    private function extendEndDateForDowngradeCredit(Carbon $currentEnd, Carbon $today, MembershipPlan $newPlan, float $credit): Carbon
    {
        if ($credit <= 0) {
            return $currentEnd;
        }

        $newPlanPeriodDays = max(1, $today->diffInDays($newPlan->calculateEndDate($today)) + 1);
        $newDailyRate = (float) $newPlan->price / $newPlanPeriodDays;
        if ($newDailyRate <= 0) {
            return $currentEnd;
        }

        $extraDays = (int) floor($credit / $newDailyRate);

        return $extraDays > 0 ? $currentEnd->copy()->addDays($extraDays) : $currentEnd;
    }

    /**
     * Full membership assignment: create the new membership (deactivating the old),
     * void the old outstanding bills, and raise a full-price bill for the new plan.
     *
     * @param Customer $customer
     * @param CustomerMembership|null $oldMembership
     * @param MembershipPlan $newPlan
     * @param Carbon $startDate
     * @return CustomerMembership
     */
    private function assignMembershipFullSwitch(Customer $customer, ?CustomerMembership $oldMembership, MembershipPlan $newPlan, Carbon $startDate): CustomerMembership
    {
        $accountId = (int) $customer->account_id;
        $membership = $this->repository->createMembership($accountId, $customer->id, $newPlan, $startDate);
        $membership->load('membershipPlan');

        if ($oldMembership) {
            $this->voidOutstandingMembershipBills($oldMembership, $customer->id, $accountId);
        }

        $this->customerBillRepository->createAutomatedBill(
            $accountId,
            $customer->id,
            $newPlan->id,
            $newPlan->price,
            $startDate
        );

        $customer->refresh();
        $customer->recalculateBalance();

        return $membership;
    }

    /**
     * Raise a prorated plan-change adjustment as a custom-amount charge.
     *
     * @param int $customerId
     * @param float $amount
     * @param string $description
     * @param GenericData $genericData
     * @return void
     */
    private function createPlanChangeAdjustmentBill(int $customerId, float $amount, string $description, GenericData $genericData): void
    {
        $genericData->data = [
            'customerId' => $customerId,
            'grossAmount' => $amount,
            'discountPercentage' => 0,
            'netAmount' => $amount,
            'paidAmount' => 0,
            'billDate' => Carbon::now(),
            'billStatus' => CustomerBillConstant::BILL_STATUS_ACTIVE,
            'billType' => CustomerBillConstant::BILL_TYPE_CUSTOM_AMOUNT,
            'customService' => $description,
            'createdBy' => $genericData->userData->id,
            'updatedBy' => $genericData->userData->id,
        ];

        $this->customerBillRepository->create($genericData);
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
            'billableId' => $membershipPlanId,
            'createdBy' => $data->createdBy ?? $genericData->userData->id,
            'updatedBy' => $data->updatedBy ?? $genericData->userData->id,
        ];

        $bill = $this->customerBillRepository->create($genericData);
        return $bill;
    }

}

