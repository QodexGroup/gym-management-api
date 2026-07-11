<?php

namespace App\Repositories\Core;

use App\Repositories\BaseRepository;

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

class CustomerRepository extends BaseRepository
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

        if (isset($genericData->filters['assignedPtCoachId'])) {
            $assignedPtCoachId = $genericData->filters['assignedPtCoachId'];
            unset($genericData->filters['assignedPtCoachId']);

            $role = (string) ($genericData->userData->role ?? '');
            $coachId = null;

            if ($role === 'coach') {
                $coachId = (int) $genericData->userData->id;
            } elseif ($assignedPtCoachId === 'self' || $assignedPtCoachId === '') {
                $coachId = (int) $genericData->userData->id;
            } elseif (is_numeric($assignedPtCoachId)) {
                $parsed = (int) $assignedPtCoachId;
                $coachId = $parsed > 0 ? $parsed : null;
            }

            if ($coachId !== null && $coachId > 0) {
                $accountId = (int) $genericData->userData->account_id;

                $query->whereIn('id', function ($sub) use ($accountId, $coachId) {
                    $sub->select('customer_id')
                        ->from((new CustomerPtPackage())->getTable())
                        ->where('account_id', $accountId)
                        ->where('coach_id', $coachId)
                        ->where('status', CustomerPtPackageConstant::STATUS_ACTIVE)
                        ->whereNull('deleted_at');
                });
            }
        }

        return $this->paginateWithGenericData($query, $genericData, ['currentMembership.membershipPlan', 'currentTrainer']);

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
                'currentMembership.pendingPlan',
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
     * Schedule a plan change to take effect at the next renewal.
     *
     * @param int $membershipId
     * @param int|null $pendingPlanId null clears a previously scheduled change
     * @return CustomerMembership
     */
    public function setPendingPlan(int $membershipId, ?int $pendingPlanId): CustomerMembership
    {
        $membership = CustomerMembership::findOrFail($membershipId);
        $membership->pending_plan_id = $pendingPlanId;
        $membership->save();

        return $membership->fresh();
    }

    /**
     * Apply a scheduled plan change: switch the membership to the new plan and
     * clear the pending flag. Used by the renewal job.
     *
     * @param int $membershipId
     * @param int $newPlanId
     * @return CustomerMembership
     */
    public function applyPendingPlan(int $membershipId, int $newPlanId): CustomerMembership
    {
        $membership = CustomerMembership::findOrFail($membershipId);
        $membership->membership_plan_id = $newPlanId;
        $membership->pending_plan_id = null;
        $membership->save();

        return $membership->fresh();
    }

    /**
     * Switch a membership to a new plan immediately (proration), optionally
     * adjusting the end date. Clears any pending scheduled change.
     *
     * @param int $membershipId
     * @param int $newPlanId
     * @param Carbon $endDate
     * @return CustomerMembership
     */
    public function changePlanImmediately(int $membershipId, int $newPlanId, Carbon $endDate): CustomerMembership
    {
        $membership = CustomerMembership::findOrFail($membershipId);
        $membership->membership_plan_id = $newPlanId;
        $membership->pending_plan_id = null;
        $membership->membership_end_date = $endDate;
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
     * Create an active membership with explicit start/end dates (used by the
     * reactivation flow where the promo length is account-configurable).
     * Account is taken from the plan to keep the signature within 4 params.
     *
     * @param int $customerId
     * @param MembershipPlan $membershipPlan
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return CustomerMembership
     */
    public function createMembershipWithDates(int $customerId, MembershipPlan $membershipPlan, Carbon $startDate, Carbon $endDate): CustomerMembership
    {
        CustomerMembership::where('customer_id', $customerId)
            ->where('status', CustomerMembershipConstant::STATUS_ACTIVE)
            ->update(['status' => CustomerMembershipConstant::STATUS_DEACTIVATED]);

        return CustomerMembership::create([
            'account_id' => $membershipPlan->account_id,
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

    /**
     * Find a non-expired membership that has a scheduled (pending) plan change to
     * the given plan. Used to apply a next-renewal switch when its renewal bill is paid.
     *
     * @param int $customerId
     * @param int $accountId
     * @param int $pendingPlanId
     * @return CustomerMembership|null
     */
    public function findMembershipWithPendingPlan(int $customerId, int $accountId, int $pendingPlanId): ?CustomerMembership
    {
        return CustomerMembership::where('customer_id', $customerId)
            ->where('account_id', $accountId)
            ->where('pending_plan_id', $pendingPlanId)
            ->where('status', '!=', CustomerMembershipConstant::STATUS_EXPIRED)
            ->orderBy('membership_end_date', 'desc')
            ->first();
    }

    /**
     * Find customer by QR code UUID
     *
     * @param string $uuid
     * @param int $accountId
     * @return Customer|null
     */
    public function findCustomerByUuid(string $uuid, int $accountId): ?Customer
    {
        return Customer::where('qr_code_uuid', $uuid)
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * @param int $accountId
     *
     * @return int
     */
    public function countByAccountId(int $accountId): int
    {
        return Customer::where('account_id', $accountId)->count();
    }

    /**
     * @param int $accountId
     *
     * @return int
     */
    public function countWithActiveMembership(int $accountId): int
    {
        return Customer::where('account_id', $accountId)
            ->whereHas('memberships', function ($q) use ($accountId) {
                $q->where('status', CustomerMembershipConstant::STATUS_ACTIVE)
                  ->where('account_id', $accountId)
                  ->whereDate('membership_end_date', '>=', today());
            })
            ->count();
    }

    /**
     * @param int $accountId
     * @param Carbon $from
     * @param Carbon $to
     *
     * @return int
     */
    public function countRegisteredBetween(int $accountId): int
    {
        return Customer::where('account_id', $accountId)
            ->whereBetween('created_at', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()])
            ->count();
    }

    /**
     * @param int $accountId
     * @param int $coachId
     *
     * @return int
     */
    public function countCoachPtClients(int $accountId, int $coachId): int
    {
        return Customer::where('account_id', $accountId)
            ->whereIn('id', function ($sub) use ($accountId, $coachId) {
                $sub->select('customer_id')
                    ->from((new CustomerPtPackage())->getTable())
                    ->where('account_id', $accountId)
                    ->where('coach_id', $coachId)
                    ->where('status', CustomerPtPackageConstant::STATUS_ACTIVE)
                    ->whereNull('deleted_at');
            })
            ->count();
    }

    /**
     * @param int $accountId
     * @param int $coachId
     *
     * @return Collection
     */
    public function getCoachPtClients(int $accountId, int $coachId): Collection
    {
        return Customer::where('account_id', $accountId)
            ->whereIn('id', function ($sub) use ($accountId, $coachId) {
                $sub->select('customer_id')
                    ->from((new CustomerPtPackage())->getTable())
                    ->where('account_id', $accountId)
                    ->where('coach_id', $coachId)
                    ->where('status', CustomerPtPackageConstant::STATUS_ACTIVE)
                    ->whereNull('deleted_at');
            })
            ->with(['currentMembership.membershipPlan'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(10)
            ->get();
    }

}

