<?php

namespace App\Repositories\Core;

use App\Repositories\BaseRepository;

use App\Constant\CustomerBillConstant;
use App\Helpers\GenericData;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CustomerBillRepository extends BaseRepository
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

        return $this->paginateWithGenericData($query, $genericData, ['creator', 'updater', 'membershipPlan']);
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

        $query->orderByDesc('bill_date');

        return $this->paginateWithGenericData($query, $genericData, ['customer', 'membershipPlan']);
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
     * Sum billed revenue (net amount of non-voided bills) for account within a date range.
     * Revenue is recognized when a client is billed, regardless of whether it has been paid yet.
     *
     * @param int $accountId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return float
     */
    public function sumBilledRevenueByDateRange(int $accountId, string $dateFrom, string $dateTo): float
    {
        return (float) CustomerBill::where('account_id', $accountId)
            ->where('bill_status', '!=', CustomerBillConstant::BILL_STATUS_VOIDED)
            ->where('bill_date', '>=', $dateFrom)
            ->where('bill_date', '<=', $dateTo)
            ->sum('net_amount');
    }

    /**
     * Sum billed revenue (net amount of non-voided bills) for account for today.
     *
     * @param int $accountId
     * @return float
     */
    public function sumBilledRevenueForToday(int $accountId): float
    {
        $today = Carbon::today()->toDateString();

        return (float) CustomerBill::where('account_id', $accountId)
            ->where('bill_status', '!=', CustomerBillConstant::BILL_STATUS_VOIDED)
            ->whereDate('bill_date', $today)
            ->sum('net_amount');
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
     * Get non-voided bills for account within date range for the Revenue report.
     * Revenue is recognized from bills, so voided bills are excluded.
     *
     * @param int $accountId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @param int|null $limit
     * @return Collection<int, CustomerBill>
     */
    public function getForRevenueExport(int $accountId, string $dateFrom, string $dateTo, ?int $limit = null): Collection
    {
        $query = CustomerBill::where('account_id', $accountId)
            ->where('bill_status', '!=', CustomerBillConstant::BILL_STATUS_VOIDED)
            ->where('bill_date', '>=', $dateFrom)
            ->where('bill_date', '<=', $dateTo)
            ->with(['customer', 'membershipPlan'])
            ->orderByDesc('bill_date')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Count non-voided bills for account within date range (for Revenue report totals).
     *
     * @param int $accountId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return int
     */
    public function countNonVoidedByAccountAndDateRange(int $accountId, string $dateFrom, string $dateTo): int
    {
        return CustomerBill::where('account_id', $accountId)
            ->where('bill_status', '!=', CustomerBillConstant::BILL_STATUS_VOIDED)
            ->where('bill_date', '>=', $dateFrom)
            ->where('bill_date', '<=', $dateTo)
            ->count();
    }

    /**
     * Sum amount already paid on non-voided bills within a date range
     * (collection realized against billed revenue).
     *
     * @param int $accountId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return float
     */
    public function sumPaidOnNonVoidedByDateRange(int $accountId, string $dateFrom, string $dateTo): float
    {
        return (float) CustomerBill::where('account_id', $accountId)
            ->where('bill_status', '!=', CustomerBillConstant::BILL_STATUS_VOIDED)
            ->where('bill_date', '>=', $dateFrom)
            ->where('bill_date', '<=', $dateTo)
            ->sum('paid_amount');
    }

    /**
     * Sum billed PT revenue (net amount) for a coach within a date range.
     *
     * @param GenericData $genericData Carries userData (coach) + startDate/endDate
     * @return float
     */
    public function getCoachPtRevenueForDateRange(GenericData $genericData): float
    {
        $data = $genericData->getData();

        return (float) CustomerBill::where('account_id', $genericData->userData->account_id)
            ->where('bill_date', '>=', $data->startDate)
            ->where('bill_date', '<=', $data->endDate)
            ->forCoachPtPackage($genericData->userData->id)
            ->sum('net_amount');
    }

    /**
     * Count PT package bills for a coach within a date range.
     *
     * @param GenericData $genericData Carries userData (coach) + startDate/endDate
     * @return int
     */
    public function countCoachPtBillsForDateRange(GenericData $genericData): int
    {
        $data = $genericData->getData();

        return CustomerBill::where('account_id', $genericData->userData->account_id)
            ->where('bill_date', '>=', $data->startDate)
            ->where('bill_date', '<=', $data->endDate)
            ->forCoachPtPackage($genericData->userData->id)
            ->count();
    }

    /**
     * Coach PT billed revenue grouped by month (last N months).
     *
     * @param int $accountId
     * @param int $coachId
     * @param int $months
     * @return array<array{month: string, revenue: float}>
     */
    public function getCoachPtRevenueByMonth(int $accountId, int $coachId, int $months = 6): array
    {
        $rows = CustomerBill::where('account_id', $accountId)
            ->forCoachPtPackage($coachId)
            ->selectRaw("DATE_FORMAT(bill_date, '%Y-%m') as month_key, SUM(net_amount) as revenue")
            ->groupBy('month_key')
            ->orderByDesc('month_key')
            ->limit($months)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'month' => Carbon::createFromFormat('Y-m', $r->month_key)->format('M'),
                'revenue' => (float) $r->revenue,
            ];
        }
        return array_reverse($out);
    }

    /**
     * Coach PT billed revenue grouped by week within a date range.
     *
     * @param int $accountId
     * @param int $coachId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return array<array{week: string, bills: int, revenue: float}>
     */
    public function getCoachPtRevenueByWeek(int $accountId, int $coachId, string $dateFrom, string $dateTo): array
    {
        $rows = CustomerBill::where('account_id', $accountId)
            ->where('bill_date', '>=', $dateFrom)
            ->where('bill_date', '<=', $dateTo)
            ->forCoachPtPackage($coachId)
            ->selectRaw("YEARWEEK(bill_date, 3) as week_key, SUM(net_amount) as revenue, COUNT(*) as bills")
            ->groupBy('week_key')
            ->orderBy('week_key')
            ->get();

        $out = [];
        $i = 1;
        foreach ($rows as $r) {
            $out[] = [
                'week' => 'Week ' . $i++,
                'bills' => (int) $r->bills,
                'revenue' => (float) $r->revenue,
            ];
        }
        return $out;
    }

    /**
     * Recent PT package bills for a coach (with customer loaded).
     *
     * @param int $accountId
     * @param int $coachId
     * @param int $limit
     * @return Collection<int, CustomerBill>
     */
    public function getRecentPtBillsForCoach(int $accountId, int $coachId, int $limit = 10): Collection
    {
        return CustomerBill::where('account_id', $accountId)
            ->forCoachPtPackage($coachId)
            ->with(['customer'])
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Coach PT bills within a date range (customer loaded), optional limit.
     * Used by the coach My Revenue report list and export.
     *
     * @param GenericData $genericData Carries userData (coach) + startDate/endDate
     * @param int|null $limit
     * @return Collection<int, CustomerBill>
     */
    public function getCoachPtBillsForDateRange(GenericData $genericData, ?int $limit = null): Collection
    {
        $data = $genericData->getData();

        $query = CustomerBill::where('account_id', $genericData->userData->account_id)
            ->where('bill_date', '>=', $data->startDate)
            ->where('bill_date', '<=', $data->endDate)
            ->forCoachPtPackage($genericData->userData->id)
            ->with(['customer'])
            ->orderByDesc('bill_date')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Sum amount already paid on a coach's PT bills within a date range.
     *
     * @param GenericData $genericData Carries userData (coach) + startDate/endDate
     * @return float
     */
    public function getCoachPtCollectedForDateRange(GenericData $genericData): float
    {
        $data = $genericData->getData();

        return (float) CustomerBill::where('account_id', $genericData->userData->account_id)
            ->where('bill_date', '>=', $data->startDate)
            ->where('bill_date', '<=', $data->endDate)
            ->forCoachPtPackage($genericData->userData->id)
            ->sum('paid_amount');
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
}
