<?php

namespace App\Repositories\Core;

use App\Constant\CustomerMembershipConstant;
use App\Models\Account\MembershipPlan;
use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerRepository
{
    /**
     * Get all customers for account_id = 1 with pagination (50 per page)
     *
     * @return LengthAwarePaginator
     */
    public function getAll(): LengthAwarePaginator
    {
        return Customer::where('account_id', 1)
            ->with(['currentMembership.membershipPlan', 'currentTrainer'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);
    }

    /**
     * Create a new customer
     *
     * @param array $data
     * @return Customer
     */
    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    /**
     * @param int $id
     *
     * @return Customer
     */
    public function getById(int $id): Customer
    {
        return Customer::where('account_id', 1)
            ->with(['currentMembership.membershipPlan', 'currentTrainer'])
            ->findOrFail($id);
    }

    /**
     * Update a customer
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $customer = Customer::where('account_id', 1)->findOrFail($id);
        return $customer->update($data);
    }

    /**
     * Delete a customer (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $customer = Customer::where('account_id', 1)->findOrFail($id);
        return $customer->delete();
    }

    /**
     * Create a membership for a customer
     *
     * @param int $accountId
     * @param int $customerId
     * @param int $membershipPlanId
     * @return CustomerMembership
     */
    public function createMembership(int $accountId, int $customerId, MembershipPlan $membershipPlan): CustomerMembership
    {
        $startDate = Carbon::now();
        $endDate = $membershipPlan->calculateEndDate($startDate);

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

