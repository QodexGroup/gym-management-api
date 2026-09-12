<?php

namespace App\Services\Platform;

use App\Constant\AccountPaymentRequestStatusConstant;
use App\Constant\PlatformConstant;
use App\Repositories\Platform\PlatformSummaryRepository;
use Illuminate\Support\Carbon;

/**
 * The dashboard counters the console shows for this product.
 *
 * "Today" is computed HERE, in the product, in the app timezone — never by the
 * console. Both products run Asia/Manila; if the console derived the day from
 * UTC it would report the wrong signups every evening after 4pm local.
 */
class PlatformSummaryService
{
    public function __construct(
        private PlatformSummaryRepository $summaryRepository,
    ) {
    }

    /**
     * @param string|null $from Inclusive start date (Y-m-d) for signupsInRange.
     * @param string|null $to Inclusive end date (Y-m-d) for signupsInRange.
     *
     * @return array<string, mixed>
     */
    public function getSummary(?string $from = null, ?string $to = null): array
    {
        $timezone = config('app.timezone');

        $rangeStart = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->subDays(29)->startOfDay();
        $rangeEnd = $to ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay();

        $byStatus = $this->summaryRepository->countAccountsByStatus();
        $overdue = $this->summaryRepository->overdueInvoiceTotals();

        // Every boundary below is built from Carbon::now(), which carries the
        // app timezone. "Today" and "this month" therefore mean today and this
        // month HERE, in the product — never in the console's clock or the
        // database session's. serverTime is returned so the operator can see
        // exactly which clock produced these numbers.
        $now = Carbon::now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $monthStart = $now->copy()->startOfMonth();

        $approved = AccountPaymentRequestStatusConstant::STATUS_APPROVED;
        $rejected = AccountPaymentRequestStatusConstant::STATUS_REJECTED;

        return [
            'signupsToday' => $this->summaryRepository->countSignupsBetween(
                Carbon::now()->startOfDay(),
                Carbon::now()->endOfDay(),
            ),
            'signupsInRange' => $this->summaryRepository->countSignupsBetween($rangeStart, $rangeEnd),
            'activeAccounts' => $byStatus[PlatformConstant::ACCOUNT_ACTIVE] ?? 0,
            'trialAccounts' => $byStatus[PlatformConstant::ACCOUNT_TRIAL] ?? 0,
            'lockedAccounts' => $byStatus[PlatformConstant::ACCOUNT_LOCKED] ?? 0,
            'deactivatedAccounts' => $byStatus[PlatformConstant::ACCOUNT_DEACTIVATED] ?? 0,
            'signupsThisMonth' => $this->summaryRepository->countSignupsBetween($monthStart, $todayEnd),
            'pendingPaymentRequests' => $this->summaryRepository->countPendingPaymentRequests(),
            'paymentsApprovedToday' => $this->summaryRepository->countDecisionsBetween($approved, $todayStart, $todayEnd),
            'paymentsRejectedToday' => $this->summaryRepository->countDecisionsBetween($rejected, $todayStart, $todayEnd),
            'paymentsApprovedThisMonth' => $this->summaryRepository->countDecisionsBetween($approved, $monthStart, $todayEnd),
            'paymentsRejectedThisMonth' => $this->summaryRepository->countDecisionsBetween($rejected, $monthStart, $todayEnd),
            'pendingInvoices' => $this->summaryRepository->countPendingInvoices(),
            'overdueInvoices' => $overdue['count'],
            'overdueAmount' => $overdue['amount'],
            'timezone' => $timezone,
            'serverTime' => $now->toIso8601String(),
            'rangeFrom' => $rangeStart->toDateString(),
            'rangeTo' => $rangeEnd->toDateString(),
        ];
    }
}
