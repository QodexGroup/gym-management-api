<?php

namespace App\Repositories\Core;

use App\Constant\CustomerMembershipConstant;
use App\Helpers\GenericData;
use App\Models\Account\MembershipPlan;
use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
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
}

