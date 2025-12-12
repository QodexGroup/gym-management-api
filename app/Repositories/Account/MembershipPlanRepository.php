<?php

namespace App\Repositories\Account;

use App\Models\Account\MembershipPlan;
use Illuminate\Database\Eloquent\Collection;

class MembershipPlanRepository
{
    /**
     * Get all membership plans for account_id = 1 with active members count
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return MembershipPlan::where('account_id', 1)
            ->withCount('activeCustomerMemberships as active_members_count')
            ->get();
    }

    /**
     * Get a membership plan by ID
     *
     * @param int $id
     * @return MembershipPlan
     */
    public function getById(int $id): MembershipPlan
    {
        return MembershipPlan::where('account_id', 1)->findOrFail($id);
    }
    /**
     * Create a new membership plan
     *
     * @param array $data
     * @return MembershipPlan
     */
    public function create(array $data): MembershipPlan
    {
        // Set account_id to 1 by default
        $data['account_id'] = 1;
        return MembershipPlan::create($data);
    }

    /**
     * Update a membership plan
     *
     * @param int $id
     * @param array $data
     * @return MembershipPlan
     */
    public function update(int $id, array $data): MembershipPlan
    {
        $plan = MembershipPlan::where('account_id', 1)->findOrFail($id);
        $plan->update($data);
        return $plan->fresh();
    }

    /**
     * Delete a membership plan (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $plan = MembershipPlan::where('account_id', 1)->findOrFail($id);
        return $plan->delete();
    }
}

