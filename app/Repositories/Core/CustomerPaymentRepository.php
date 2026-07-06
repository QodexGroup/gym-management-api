<?php

namespace App\Repositories\Core;

use App\Helpers\GenericData;
use App\Models\Core\CustomerPayment;
use App\Repositories\BaseRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class CustomerPaymentRepository extends BaseRepository
{
    /**
     * Create a new customer payment.
     *
     * @param GenericData $genericData
     * @return CustomerPayment
     */
    public function create(GenericData $genericData): CustomerPayment
    {
        // Ensure account_id, createdBy, and updatedBy are set in data
        $genericData->getData()->accountId = $genericData->userData->account_id;
        $genericData->getData()->createdBy = $genericData->getData()->createdBy ?? $genericData->userData->id;
        $genericData->getData()->updatedBy = $genericData->getData()->updatedBy ?? $genericData->userData->id;
        $genericData->syncDataArray();

        return CustomerPayment::create($genericData->data);
    }

    /**
     * Get a payment by id and account id.
     *
     * @param int $id
     * @param int $accountId
     * @return CustomerPayment
     */
    public function getById(int $id, int $accountId): CustomerPayment
    {
        return CustomerPayment::where('id', $id)
            ->where('account_id', $accountId)
            ->with(['bill'])
            ->firstOrFail();
    }

    /**
     * Get all payments for a bill.
     *
     * @param int $billId
     * @param int $accountId
     * @return Collection<int, CustomerPayment>
     */
    public function getByBillId(int $billId, int $accountId): Collection
    {
        return CustomerPayment::where('account_id', $accountId)
            ->where('customer_bill_id', $billId)
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    /**
     * Delete a payment (soft delete).
     *
     * @param int $id
     * @param int $customerBillId
     * @param int $accountId
     * @return CustomerPayment
     */
    public function delete(int $id, int $customerBillId, int $accountId): CustomerPayment
    {
        $payment = CustomerPayment::where('id', $id)
            ->where('account_id', $accountId)
            ->where('customer_bill_id', $customerBillId)
            ->firstOrFail();
        $payment->delete();

        return $payment;
    }

    /**
     * Sum PT package payment amounts for a coach in date range (for My Collection).
     * Uses actual payments made, so partial payments are included.
     * Uses the assigned coach_id from CustomerPtPackage.
     *
     * @param GenericData $genericData Carries userData (coach) + startDate/endDate
     * @return float
     */
    public function getCoachPtEarningsForDateRange(GenericData $genericData): float
    {
        $data = $genericData->getData();

        $sum = CustomerPayment::where('account_id', $genericData->userData->account_id)
            ->where('payment_date', '>=', $data->startDate)
            ->where('payment_date', '<=', $data->endDate)
            ->forCoachPtPackage($genericData->userData->id)
            ->sum('amount');

        return (float) $sum;
    }

    /**
     * Count PT package payments for a coach in date range.
     *
     * @param GenericData $genericData Carries userData (coach) + startDate/endDate
     * @return int
     */
    public function countCoachPtPaymentsForDateRange(GenericData $genericData): int
    {
        $data = $genericData->getData();

        return CustomerPayment::where('account_id', $genericData->userData->account_id)
            ->where('payment_date', '>=', $data->startDate)
            ->where('payment_date', '<=', $data->endDate)
            ->forCoachPtPackage($genericData->userData->id)
            ->count();
    }

    /**
     * Coach PT earnings grouped by month (last N months).
     *
     * @param int $accountId
     * @param int $coachId
     * @param int $months
     * @return array<array{month: string, earnings: float}>
     */
    public function getCoachPtEarningsByMonth(int $accountId, int $coachId, int $months = 6): array
    {
        $rows = CustomerPayment::where('account_id', $accountId)
            ->forCoachPtPackage($coachId)
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as month_key, SUM(amount) as earnings")
            ->groupBy('month_key')
            ->orderByDesc('month_key')
            ->limit($months)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'month' => Carbon::createFromFormat('Y-m', $r->month_key)->format('M'),
                'earnings' => (float) $r->earnings,
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
     * @return array<array{week: string, payments: int, earnings: float}>
     */
    public function getCoachPtEarningsByWeek(int $accountId, int $coachId, string $dateFrom, string $dateTo): array
    {
        $rows = CustomerPayment::where('account_id', $accountId)
            ->where('payment_date', '>=', $dateFrom)
            ->where('payment_date', '<=', $dateTo)
            ->forCoachPtPackage($coachId)
            ->selectRaw("YEARWEEK(payment_date, 3) as week_key, SUM(amount) as earnings, COUNT(*) as payments")
            ->groupBy('week_key')
            ->orderBy('week_key')
            ->get();

        $out = [];
        $i = 1;
        foreach ($rows as $r) {
            $out[] = [
                'week' => 'Week ' . $i++,
                'payments' => (int) $r->payments,
                'earnings' => (float) $r->earnings,
            ];
        }
        return $out;
    }

    /**
     * Get recent PT payments for a coach (for My Collection recent payments).
     * Returns payments for PT package bills only.
     *
     * @param int $accountId
     * @param int $coachId
     * @param int $limit
     * @return Collection
     */
    public function getRecentPtPaymentsForCoach(int $accountId, int $coachId, int $limit = 10): Collection
    {
        return CustomerPayment::where('account_id', $accountId)
            ->forCoachPtPackage($coachId)
            ->with(['customer'])
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Coach PT payments within a date range (customer + bill loaded), optional limit.
     * Used by the coach My Collection report list and export.
     *
     * @param GenericData $genericData Carries userData (coach) + startDate/endDate
     * @param int|null $limit
     * @return Collection<int, CustomerPayment>
     */
    public function getCoachPtPaymentsForDateRange(GenericData $genericData, ?int $limit = null): Collection
    {
        $data = $genericData->getData();

        $query = CustomerPayment::where('account_id', $genericData->userData->account_id)
            ->where('payment_date', '>=', $data->startDate)
            ->where('payment_date', '<=', $data->endDate)
            ->forCoachPtPackage($genericData->userData->id)
            ->with(['customer', 'bill'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Count payments for account within date range (for report export size check).
     * Uses payment_date for filtering.
     *
     * @param int $accountId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return int
     */
    public function countByAccountAndDateRange(int $accountId, string $dateFrom, string $dateTo): int
    {
        return CustomerPayment::where('account_id', $accountId)
            ->where('payment_date', '>=', $dateFrom)
            ->where('payment_date', '<=', $dateTo)
            ->count();
    }

    /**
     * Get all payments for account within date range for report export (no pagination).
     * Uses payment_date for filtering. Eager loads customer and bill (for bill_type).
     *
     * @param int $accountId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @param int|null $limit optional limit (e.g. for API response)
     * @return Collection<int, CustomerPayment>
     */
    public function getForExport(int $accountId, string $dateFrom, string $dateTo, ?int $limit = null): Collection
    {
        $query = CustomerPayment::where('account_id', $accountId)
            ->where('payment_date', '>=', $dateFrom)
            ->where('payment_date', '<=', $dateTo)
            ->with(['customer', 'bill'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Sum payment amounts for account within date range (for report totals).
     *
     * @param int $accountId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return float
     */
    public function sumByAccountAndDateRange(int $accountId, string $dateFrom, string $dateTo): float
    {
        return (float) CustomerPayment::where('account_id', $accountId)
            ->where('payment_date', '>=', $dateFrom)
            ->where('payment_date', '<=', $dateTo)
            ->sum('amount');
    }

    /**
     * Sum payment amounts for account for today (for report "Today's Revenue").
     *
     * @param int $accountId
     * @return float
     */
    public function getTodayRevenueByAccount(int $accountId): float
    {
        $today = Carbon::today()->toDateString();
        return (float) CustomerPayment::where('account_id', $accountId)
            ->whereDate('payment_date', $today)
            ->sum('amount');
    }
}
