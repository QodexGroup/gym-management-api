<?php

namespace App\Repositories\Core;

use App\Constant\CustomerMembershipConstant;
use App\Constant\CustomerPtPackageConstant;
use App\Helpers\GenericData;
use App\Models\Account\MembershipPlan;
use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use App\Models\Core\CustomerPtPackage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerRepository
{
    /**
     * Get all customers with pagination, filtering, sorting, and relations
     *
     * @param GenericData $genericData
     * @return LengthAwarePaginator
     */
    public function getAll(GenericData $genericData): LengthAwarePaginator
    {
        $query = Customer::where('account_id', $genericData->userData->account_id);

        // Handle search filter separately (searches across multiple fields)
        if (isset($genericData->filters['search']) && !empty($genericData->filters['search'])) {
            $searchTerm = $genericData->filters['search'];
            unset($genericData->filters['search']); // Remove from filters to avoid double processing

            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTerm}%"]);
            });
        }

        // Apply relations, filters, and sorts using GenericData methods
        $query = $genericData->applyRelations($query, ['currentMembership.membershipPlan', 'currentTrainer']);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);

        return $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);

    }

    /**
     * Create a new customer
     *
     * @param GenericData $genericData
     * @return Customer
     */
    public function create(GenericData $genericData): Customer
    {
        // Ensure account_id is set in data
        $genericData->getData()->account_id = $genericData->userData->account_id;
        $genericData->syncDataArray();

        return Customer::create($genericData->data)->fresh();
    }

    /**
     * Get a customer by ID
     *
     * @param int $id
     * @param int $accountId
     * @return Customer
     */
    public function findCustomerById(int $id, int $accountId): Customer
    {
        return Customer::where('id', $id)
            ->where('account_id', $accountId)
            ->with([
                'currentMembership.membershipPlan',
                'currentTrainer'
            ])
            ->firstOrFail();
    }

    /**
     * Update a customer
     *
     * @param int $id
     * @param GenericData $genericData
     * @return Customer
     */
    public function update(int $id, GenericData $genericData): Customer
    {
        $customer = $this->findCustomerById($id, $genericData->userData->account_id);
        $customer->update($genericData->data);
        return $customer->fresh();
    }

    /**
     * Delete a customer (soft delete)
     *
     * @param int $id
     * @param int $accountId
     * @return bool
     */
    public function delete(int $id, int $accountId): bool
    {
        $customer = $this->findCustomerById($id, $accountId);
        return $customer->delete();
    }

    /**
     * Create a membership for a customer
     *
     * @param int $accountId
     * @param int $customerId
     * @param MembershipPlan $membershipPlan
     * @param Carbon|null $startDate
     * @return CustomerMembership
     */
    public function createMembership(int $accountId, int $customerId, MembershipPlan $membershipPlan, ?Carbon $startDate = null): CustomerMembership
    {
        $startDate = $startDate ?? Carbon::now();
        $endDate = $membershipPlan->calculateEndDate($startDate);

        // Deactivate existing active memberships
        CustomerMembership::where('customer_id', $customerId)
            ->where('status', CustomerMembershipConstant::STATUS_ACTIVE)
            ->update(['status' => CustomerMembershipConstant::STATUS_DEACTIVATED]);

        return CustomerMembership::create([
            'account_id' => $accountId,
            'customer_id' => $customerId,
            'membership_plan_id' => $membershipPlan->id,
            'membership_start_date' => $startDate,
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);
    }

    /**
     * Get expired membership plan IDs for a customer
     *
     * @param int $customerId
     * @param int $accountId
     * @return array
     */
    public function getExpiredMembershipPlanIds(int $customerId, int $accountId): array
    {
        return CustomerMembership::where('customer_id', $customerId)
            ->where('account_id', $accountId)
            ->where(function ($query) {
                $query->whereDate('membership_end_date', '<', today())
                    ->orWhere('status', CustomerMembershipConstant::STATUS_EXPIRED);
            })
            ->pluck('membership_plan_id')
            ->toArray();
    }

    /**
     * Extend membership dates for renewal
     *
     * @param int $membershipId
     * @param Carbon $newStartDate
     * @param Carbon $newEndDate
     * @return CustomerMembership
     */
    public function extendMembership(int $membershipId, Carbon $newStartDate, Carbon $newEndDate): CustomerMembership
    {
        $membership = CustomerMembership::findOrFail($membershipId);
        $membership->membership_start_date = $newStartDate;
        $membership->membership_end_date = $newEndDate;
        $membership->status = CustomerMembershipConstant::STATUS_ACTIVE;
        $membership->save();

        return $membership->fresh();
    }

    /**
     * Get the last expired membership for a customer
     *
     * @param int $customerId
     * @param int $accountId
     * @return CustomerMembership|null
     */
    public function getLastExpiredMembership(int $customerId, int $accountId): ?CustomerMembership
    {
        return CustomerMembership::where('customer_id', $customerId)
            ->where('account_id', $accountId)
            ->where(function ($query) {
                $query->whereDate('membership_end_date', '<', today())
                    ->orWhere('status', CustomerMembershipConstant::STATUS_EXPIRED);
            })
            ->orderBy('membership_end_date', 'desc')
            ->first();
    }

    /**
     * Create membership with free month (for reactivation)
     *
     * @param int $accountId
     * @param int $customerId
     * @param MembershipPlan $membershipPlan
     * @param Carbon $startDate
     * @return CustomerMembership
     */
    public function createMembershipWithFreeMonth(int $accountId, int $customerId, MembershipPlan $membershipPlan, Carbon $startDate): CustomerMembership
    {
        // Free month = 1 month from start date (regardless of plan type)
        $endDate = $startDate->copy()->addMonth();

        // Deactivate existing active memberships
        CustomerMembership::where('customer_id', $customerId)
            ->where('status', CustomerMembershipConstant::STATUS_ACTIVE)
            ->update(['status' => CustomerMembershipConstant::STATUS_DEACTIVATED]);

        return CustomerMembership::create([
            'account_id' => $accountId,
            'customer_id' => $customerId,
            'membership_plan_id' => $membershipPlan->id,
            'membership_start_date' => $startDate,
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);
    }

    /**
     * Find the latest membership for a customer and plan (excluding expired)
     *
     * @param int $customerId
     * @param int $accountId
     * @param int $membershipPlanId
     * @return CustomerMembership|null
     */
    public function findLatestMembershipForPlan(int $customerId, int $accountId, int $membershipPlanId): ?CustomerMembership
    {
        return CustomerMembership::where('customer_id', $customerId)
            ->where('account_id', $accountId)
            ->where('membership_plan_id', $membershipPlanId)
            ->where('status', '!=', CustomerMembershipConstant::STATUS_EXPIRED)
            ->orderBy('membership_end_date', 'desc')
            ->first();
    }


}

