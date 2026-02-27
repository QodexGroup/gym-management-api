<?php

namespace App\Repositories\Core;

use App\Constant\CustomerBillConstant;
use App\Helpers\GenericData;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CustomerBillRepository
{
    /**
     * Create a new bill
     *
     * @param GenericData $genericData
     * @return CustomerBill
     */
    public function create(GenericData $genericData): CustomerBill
    {
        // Ensure account_id is set in data
        $genericData->getData()->accountId = $genericData->userData->account_id;
        $genericData->getData()->paidAmount = $genericData->getData()->paidAmount ?? 0;
        $genericData->getData()->discountPercentage = $genericData->getData()->discountPercentage ?? 0;
        $genericData->getData()->createdBy = $genericData->getData()->createdBy ?? $genericData->userData->id;
        $genericData->getData()->updatedBy = $genericData->getData()->updatedBy ?? $genericData->userData->id;
        $genericData->syncDataArray();

        return CustomerBill::create($genericData->data)->fresh();
    }

    /**
     * Create an automated bill for membership renewal
     *
     * @param int $accountId
     * @param int $customerId
     * @param int $membershipPlanId
     * @param float $grossAmount
     * @param Carbon $billDate
     * @return CustomerBill
     */
    public function createAutomatedBill(int $accountId, int $customerId, int $membershipPlanId, float $grossAmount, Carbon $billDate): CustomerBill
    {
        return CustomerBill::create([
            'account_id' => $accountId,
            'customer_id' => $customerId,
            'billable_id' => $membershipPlanId,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_status' => CustomerBillConstant::BILL_STATUS_ACTIVE,
            'bill_date' => $billDate,
            'billing_period' => $billDate->format('mdY'),
            'gross_amount' => $grossAmount,
            'discount_percentage' => 0,
            'net_amount' => $grossAmount,
            'paid_amount' => 0,
        ]);
    }

    /**
     * @param GenericData $genericData
     *
     * @return CustomerBill
     */
    public function createAutomatedBillForPtPackage(GenericData $genericData): CustomerBill
    {
        $genericData->getData()->accountId = $genericData->userData->account_id;
        $genericData->getData()->createdBy =  $genericData->userData->id;
        $genericData->getData()->updatedBy = $genericData->userData->id;
        $genericData->getData()->billType = CustomerBillConstant::BILL_TYPE_PT_PACKAGE_SUBSCRIPTION;
        $genericData->getData()->billStatus = CustomerBillConstant::BILL_STATUS_ACTIVE;
        $genericData->getData()->billDate = Carbon::now();
        $genericData->getData()->paidAmount = 0;
        $genericData->getData()->discountPercentage = 0;
        $genericData->getData()->billableId = $genericData->getData()->ptPackageId;
        $genericData->syncDataArray();

        return CustomerBill::create($genericData->data)->fresh();
    }

    /**
     * Check if an automated bill exists for a membership renewal period
     *
     * @param int $customerId
     * @param int $accountId
     * @param int $membershipPlanId
     * @param Carbon $expectedBillDate
     * @return bool
     */
    public function automatedBillExists(int $customerId, int $accountId, int $membershipPlanId, Carbon $expectedBillDate): bool
    {
        return CustomerBill::where('customer_id', $customerId)
            ->where('account_id', $accountId)
            ->where('billable_id', $membershipPlanId)
            ->where('bill_type', CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION)
            ->whereDate('bill_date', $expectedBillDate->toDateString())
            ->exists();
    }

    /**
     * Find automated bill for a membership renewal period
     *
     * @param int $customerId
     * @param int $accountId
     * @param int $membershipPlanId
     * @param Carbon $expectedBillDate
     * @return CustomerBill|null
     */
    public function findAutomatedBill(int $customerId, int $accountId, int $membershipPlanId, Carbon $expectedBillDate): ?CustomerBill
    {
        return CustomerBill::where('customer_id', $customerId)
            ->where('account_id', $accountId)
            ->where('billable_id', $membershipPlanId)
            ->where('bill_type', CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION)
            ->whereDate('bill_date', $expectedBillDate->toDateString())
            ->first();
    }

    /**
     * Find a bill by ID and account ID
     *
     * @param int $id
     * @param int $accountId
     * @return CustomerBill
     */
    public function findBillById(int $id, int $accountId): CustomerBill
    {
        return CustomerBill::where('id', $id)
            ->where('account_id', $accountId)
            ->with(['creator', 'updater', 'membershipPlan'])
            ->firstOrFail();
    }

    /**
     * Update a bill
     *
     * @param int $id
     * @param GenericData $genericData
     * @return CustomerBill
     */
    public function update(int $id, GenericData $genericData): CustomerBill
    {
        $bill = $this->findBillById($id, $genericData->userData->account_id);
        // Set updatedBy if not provided
        $genericData->getData()->updatedBy = $genericData->getData()->updatedBy ?? $genericData->userData->id;
        $genericData->syncDataArray();
        $bill->update($genericData->data);
        return $bill->fresh(['creator', 'updater', 'membershipPlan']);
    }

    /**
     * Update bill paid amount and status (used by payments)
     *
     * @param int $id
     * @param int $accountId
     * @param float $paidAmount
     * @param string $status
     * @param int $updatedBy
     * @return CustomerBill
     */
    public function updatePaidAmount(int $id, int $accountId, float $paidAmount, string $status, int $updatedBy): CustomerBill
    {
        $bill = $this->findBillById($id, $accountId);
        $bill->paid_amount = $paidAmount;
        $bill->bill_status = $status;
        $bill->updated_by = $updatedBy;
        $bill->save();

        return $bill->fresh(['creator', 'updater']);
    }

    /**
     * Delete a bill
     *
     * @param int $id
     * @param int $accountId
     * @return bool
     */
    public function delete(int $id, int $accountId): bool
    {
        $bill = $this->findBillById($id, $accountId);
        return $bill->delete();
    }

    /**
     * Get bills by customer ID with pagination, filtering, and sorting
     *
     * @param int $customerId
     * @param GenericData $genericData
     * @return LengthAwarePaginator
     */
    public function getByCustomerId(GenericData $genericData): LengthAwarePaginator
    {
        $query = CustomerBill::where('customer_id', $genericData->customerId)
            ->where('account_id', $genericData->userData->account_id);

        // Apply relations, filters, and sorts using GenericData methods
        $query = $genericData->applyRelations($query, ['creator', 'updater', 'membershipPlan']);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);

        // Always return paginated results
        return $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
    }

    /**
     * Get all bills for the account (for reports) with pagination, filtering, and sorting.
     * Optionally load customer relation for customer name.
     *
     * @param GenericData $genericData
     * @return LengthAwarePaginator
     */
    public function getByAccountId(GenericData $genericData): LengthAwarePaginator
    {
        $query = CustomerBill::where('account_id', $genericData->userData->account_id);

        $filters = $genericData->filters ?? [];
        $dateFrom = $filters['dateFrom'] ?? $filters['date_from'] ?? null;
        $dateTo = $filters['dateTo'] ?? $filters['date_to'] ?? null;
        if ($dateFrom) {
            $query->where('bill_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('bill_date', '<=', $dateTo);
        }
        $genericData->filters = array_diff_key($filters, array_flip(['dateFrom', 'dateTo', 'date_from', 'date_to']));

        $query = $genericData->applyRelations($query, ['customer', 'membershipPlan']);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);

        return $query->orderByDesc('bill_date')->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
    }

    /**
     * Count bills for account within date range (for report export size check).
     *
     * @param int $accountId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return int
     */
    public function countByAccountAndDateRange(int $accountId, string $dateFrom, string $dateTo): int
    {
        return CustomerBill::where('account_id', $accountId)
            ->where('bill_date', '>=', $dateFrom)
            ->where('bill_date', '<=', $dateTo)
            ->count();
    }

    /**
     * Get all bills for account within date range for export (no pagination).
     *
     * @param int $accountId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return Collection<int, CustomerBill>
     */
    public function getForExport(int $accountId, string $dateFrom, string $dateTo): Collection
    {
        return CustomerBill::where('account_id', $accountId)
            ->where('bill_date', '>=', $dateFrom)
            ->where('bill_date', '<=', $dateTo)
            ->with(['customer', 'membershipPlan'])
            ->orderByDesc('bill_date')
            ->get();
    }

    /**
     * Find expired membership bills with outstanding balance
     *
     * @param int $customerId
     * @param int $accountId
     * @param array $expiredMembershipPlanIds
     * @return Collection
     */
    public function findExpiredMembershipBillsWithOutstandingBalance(int $customerId, int $accountId, array $expiredMembershipPlanIds): Collection
    {
        if (empty($expiredMembershipPlanIds)) {
            return collect([]);
        }

        return CustomerBill::where('customer_id', $customerId)
            ->where('account_id', $accountId)
            ->where('bill_type', CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION)
            ->whereIn('billable_id', $expiredMembershipPlanIds)
            ->where('bill_status', '!=', CustomerBillConstant::BILL_STATUS_VOIDED)
            ->whereRaw('net_amount > paid_amount')
            ->get();
    }

    /**
     * Void a bill by setting net_amount to paid_amount and status to voided
     *
     * @param int $billId
     * @param int $accountId
     * @param float $paidAmount
     * @return CustomerBill
     */
    public function voidBill(int $billId, int $accountId): CustomerBill
    {
        $bill = $this->findBillById($billId, $accountId);
        $bill->bill_status = CustomerBillConstant::BILL_STATUS_VOIDED;
        $bill->save();

        return $bill->fresh();
    }

    /**
     * Find membership subscription bills with outstanding balance for a specific membership plan
     *
     * @param int $customerId
     * @param int $accountId
     * @param int $membershipPlanId
     * @return Collection
     */
    public function findMembershipBillsWithOutstandingBalance(int $customerId, int $accountId, int $membershipPlanId): Collection
    {
        return CustomerBill::where('customer_id', $customerId)
            ->where('account_id', $accountId)
            ->where('billable_id', $membershipPlanId)
            ->where('bill_type', CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION)
            ->where('bill_status', '!=', CustomerBillConstant::BILL_STATUS_VOIDED)
            ->whereRaw('net_amount > paid_amount')
            ->get();
    }

    /**
     * Sum PT package bill paid_amount for a coach in date range (for My Collection).
     *
     * @param int $accountId
     * @param int $coachId created_by user id
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return float
     */
    public function getCoachPtEarningsForDateRange(int $accountId, int $coachId, string $dateFrom, string $dateTo): float
    {
        $sum = CustomerBill::where('account_id', $accountId)
            ->where('created_by', $coachId)
            ->where('bill_type', CustomerBillConstant::BILL_TYPE_PT_PACKAGE_SUBSCRIPTION)
            ->where('bill_status', '!=', CustomerBillConstant::BILL_STATUS_VOIDED)
            ->where('bill_date', '>=', $dateFrom)
            ->where('bill_date', '<=', $dateTo)
            ->sum('paid_amount');

        return (float) $sum;
    }

    /**
     * Coach PT earnings grouped by month (last N months).
     *
     * @param int $accountId
     * @param int $coachId
     * @param int $months
     * @return array<array{month: string, earnings: float, target: float|null}>
     */
    public function getCoachPtEarningsByMonth(int $accountId, int $coachId, int $months = 6): array
    {
        $rows = CustomerBill::where('account_id', $accountId)
            ->where('created_by', $coachId)
            ->where('bill_type', CustomerBillConstant::BILL_TYPE_PT_PACKAGE_SUBSCRIPTION)
            ->where('bill_status', '!=', CustomerBillConstant::BILL_STATUS_VOIDED)
            ->selectRaw("DATE_FORMAT(bill_date, '%Y-%m') as month_key, SUM(paid_amount) as earnings")
            ->groupBy('month_key')
            ->orderByDesc('month_key')
            ->limit($months)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'month' => Carbon::createFromFormat('Y-m', $r->month_key)->format('M'),
                'earnings' => (float) $r->earnings,
                'target' => null,
            ];
        }
        return array_reverse($out);
    }

    /**
     * Coach PT earnings grouped by week within date range.
     *
     * @param int $accountId
     * @param int $coachId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return array<array{week: string, sessions: int, earnings: float}>
     */
    public function getCoachPtEarningsByWeek(int $accountId, int $coachId, string $dateFrom, string $dateTo): array
    {
        $rows = CustomerBill::where('account_id', $accountId)
            ->where('created_by', $coachId)
            ->where('bill_type', CustomerBillConstant::BILL_TYPE_PT_PACKAGE_SUBSCRIPTION)
            ->where('bill_status', '!=', CustomerBillConstant::BILL_STATUS_VOIDED)
            ->where('bill_date', '>=', $dateFrom)
            ->where('bill_date', '<=', $dateTo)
            ->selectRaw("YEARWEEK(bill_date, 3) as week_key, SUM(paid_amount) as earnings, COUNT(*) as sessions")
            ->groupBy('week_key')
            ->orderBy('week_key')
            ->get();

        $out = [];
        $i = 1;
        foreach ($rows as $r) {
            $out[] = [
                'week' => 'Week ' . $i++,
                'sessions' => (int) $r->sessions,
                'earnings' => (float) $r->earnings,
            ];
        }
        return $out;
    }

}
