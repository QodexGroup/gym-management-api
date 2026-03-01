<?php

namespace App\Repositories\Core;

use App\Constant\CustomerBillConstant;
use App\Helpers\GenericData;
use App\Models\Core\CustomerPayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class CustomerPaymentRepository
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
     * @param int $accountId
     * @param int $coachId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return float
     */
    public function getCoachPtEarningsForDateRange(int $accountId, int $coachId, string $dateFrom, string $dateTo): float
    {
        $sum = CustomerPayment::where('account_id', $accountId)
            ->where('payment_date', '>=', $dateFrom)
            ->where('payment_date', '<=', $dateTo)
            ->whereHas('bill', function ($q) use ($coachId) {
                $q->where('bill_type', CustomerBillConstant::BILL_TYPE_PT_PACKAGE_SUBSCRIPTION)
                  ->where('bill_status', '!=', CustomerBillConstant::BILL_STATUS_VOIDED)
                  ->whereHas('customerPtPackage', function ($ptQ) use ($coachId) {
                      $ptQ->where('coach_id', $coachId);
                  });
            })
            ->sum('amount');

        return (float) $sum;
    }

    /**
     * Count PT package payments for a coach in date range.
     *
     * @param int $accountId
     * @param int $coachId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return int
     */
    public function countCoachPtPaymentsForDateRange(int $accountId, int $coachId, string $dateFrom, string $dateTo): int
    {
        return CustomerPayment::where('account_id', $accountId)
            ->where('payment_date', '>=', $dateFrom)
            ->where('payment_date', '<=', $dateTo)
            ->whereHas('bill', function ($q) use ($coachId) {
                $q->where('bill_type', CustomerBillConstant::BILL_TYPE_PT_PACKAGE_SUBSCRIPTION)
                  ->where('bill_status', '!=', CustomerBillConstant::BILL_STATUS_VOIDED)
                  ->whereHas('customerPtPackage', function ($ptQ) use ($coachId) {
                      $ptQ->where('coach_id', $coachId);
                  });
            })
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
            ->whereHas('bill', function ($q) use ($coachId) {
                $q->where('bill_type', CustomerBillConstant::BILL_TYPE_PT_PACKAGE_SUBSCRIPTION)
                  ->where('bill_status', '!=', CustomerBillConstant::BILL_STATUS_VOIDED)
                  ->whereHas('customerPtPackage', function ($ptQ) use ($coachId) {
                      $ptQ->where('coach_id', $coachId);
                  });
            })
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
            ->whereHas('bill', function ($q) use ($coachId) {
                $q->where('bill_type', CustomerBillConstant::BILL_TYPE_PT_PACKAGE_SUBSCRIPTION)
                  ->where('bill_status', '!=', CustomerBillConstant::BILL_STATUS_VOIDED)
                  ->whereHas('customerPtPackage', function ($ptQ) use ($coachId) {
                      $ptQ->where('coach_id', $coachId);
                  });
            })
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
            ->whereHas('bill', function ($q) use ($coachId) {
                $q->where('bill_type', CustomerBillConstant::BILL_TYPE_PT_PACKAGE_SUBSCRIPTION)
                  ->where('bill_status', '!=', CustomerBillConstant::BILL_STATUS_VOIDED)
                  ->whereHas('customerPtPackage', function ($ptQ) use ($coachId) {
                      $ptQ->where('coach_id', $coachId);
                  });
            })
            ->with(['customer'])
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
