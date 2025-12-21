<?php

namespace App\Repositories\Account;

use App\Helpers\GenericData;
use App\Models\Account\MembershipPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MembershipPlanRepository
{
    /**
     * Get all membership plans with active members count
     *
     * @param GenericData $genericData
     * @return LengthAwarePaginator|Collection
     */
    public function getAllMembershipPlans(GenericData $genericData): LengthAwarePaginator|Collection
    {
        $query = MembershipPlan::where('account_id', $genericData->userData->account_id)
            ->withCount('activeCustomerMemberships as active_members_count');

        // Apply relations, filters, and sorts using GenericData methods
        $query = $genericData->applyRelations($query);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);

        // Check if pagination is requested
        if ($genericData->pageSize > 0) {
            return $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
        }

        return $query->get();
    }

    /**
     * Get a membership plan by ID
     *
     * @param int $id
     * @param int $accountId
     * @return MembershipPlan
     */
    public function findMembershipPlanById(int $id, int $accountId): MembershipPlan
    {
        return MembershipPlan::where('id', $id)
            ->where('account_id', $accountId)
            ->firstOrFail();
    }

    /**
     * Create a new membership plan
     *
     * @param GenericData $genericData
     * @return MembershipPlan
     */
    public function createMembershipPlan(GenericData $genericData): MembershipPlan
    {
        // Ensure account_id is set in data
        $genericData->getData()->account_id = $genericData->userData->account_id;
        $genericData->syncDataArray();

        return MembershipPlan::create($genericData->data)->fresh();
    }

    /**
     * Update a membership plan
     *
     * @param int $id
     * @param GenericData $genericData
     * @return MembershipPlan
     */
    public function updateMembershipPlan(int $id, GenericData $genericData): MembershipPlan
    {
        $plan = $this->findMembershipPlanById($id, $genericData->userData->account_id);
        $plan->update($genericData->data);
        return $plan->fresh();
    }

    /**
     * Delete a membership plan (soft delete)
     *
     * @param int $id
     * @param int $accountId
     * @return bool
     */
    public function deleteMembershipPlan(int $id, int $accountId): bool
    {
        $plan = $this->findMembershipPlanById($id, $accountId);
        return $plan->delete();
    }
}

