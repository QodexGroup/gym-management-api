<?php

namespace App\Repositories\Platform;

use App\Constant\AccountInvoiceStatusConstant;
use App\Constant\AccountPaymentRequestStatusConstant;
use App\Support\Platform\AccountStatusResolver;
use App\Support\Platform\InvoiceStatusResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard counters, computed with aggregates rather than by loading rows.
 *
 * Every date boundary here is evaluated in the app timezone (Asia/Manila) by
 * PHP, never by MySQL — the database session may well be on UTC, and an
 * 8-hour offset would silently mis-report "today" every evening.
 */
class PlatformSummaryRepository
{
    /**
     * Accounts grouped by their derived status.
     *
     * @return array<string, int> Keyed by status; absent statuses are omitted.
     */
    public function countAccountsByStatus(): array
    {
        [$statusSql, $statusBindings] = AccountStatusResolver::sqlExpression();

        $inner = DB::table('accounts')->selectRaw("({$statusSql}) as derived_status", $statusBindings);
        AccountStatusResolver::latestPlanJoin($inner);

        return DB::query()
            ->fromSub($inner, 'derived')
            ->selectRaw('derived.derived_status as status, COUNT(*) as total')
            ->groupBy('derived.derived_status')
            ->pluck('total', 'status')
            ->map(static fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Accounts created between two instants.
     *
     * @param Carbon $from
     * @param Carbon $to
     *
     * @return int
     */
    public function countSignupsBetween(Carbon $from, Carbon $to): int
    {
        return DB::table('accounts')
            ->whereBetween('accounts.created_at', [$from, $to])
            ->count();
    }

    /**
     * @return int
     */
    public function countPendingPaymentRequests(): int
    {
        return DB::table('account_payment_requests')
            ->where('status', AccountPaymentRequestStatusConstant::STATUS_PENDING)
            ->count();
    }

    /**
     * Payment requests that reached the given decision inside a window.
     *
     * ⚠️ `approved_at` is the column, but markAsRejected() writes it too — it is
     * really "decided_at" under a misleading name. That is what makes a rejected
     * count possible at all; there is no rejected_at. Filtering on `status` as
     * well is what separates the two.
     *
     * @param string $status
     * @param Carbon $from
     * @param Carbon $to
     *
     * @return int
     */
    public function countDecisionsBetween(string $status, Carbon $from, Carbon $to): int
    {
        return DB::table('account_payment_requests')
            ->where('status', $status)
            ->whereNotNull('approved_at')
            ->whereBetween('approved_at', [$from, $to])
            ->count();
    }

    /**
     * Unpaid invoices that are NOT yet overdue.
     *
     * Pending and overdue partition the unpaid set rather than overlapping, so
     * this is the exact complement of overdueInvoiceTotals() — adding the two
     * gives every unpaid invoice, and neither double-counts.
     *
     * @return int
     */
    public function countPendingInvoices(): int
    {
        [$overdueSql, $overdueBindings] = InvoiceStatusResolver::overdueSqlCondition();

        return DB::table('account_invoices')
            ->where('account_invoices.status', AccountInvoiceStatusConstant::STATUS_PENDING)
            ->whereRaw("NOT ({$overdueSql})", $overdueBindings)
            ->count();
    }

    /**
     * Overdue invoices and what they add up to, in one pass.
     *
     * @return array{count: int, amount: float}
     */
    public function overdueInvoiceTotals(): array
    {
        [$overdueSql, $overdueBindings] = InvoiceStatusResolver::overdueSqlCondition();

        $row = DB::table('account_invoices')
            ->whereRaw($overdueSql, $overdueBindings)
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(account_invoices.total_amount), 0) as amount')
            ->first();

        return [
            'count' => (int) ($row->total ?? 0),
            'amount' => round((float) ($row->amount ?? 0), 2),
        ];
    }
}
