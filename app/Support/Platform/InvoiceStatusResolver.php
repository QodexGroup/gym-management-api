<?php

namespace App\Support\Platform;

use App\Constant\AccountInvoiceStatusConstant;
use App\Constant\BillingCycleConstant;
use App\Constant\PlatformConstant;
use App\Models\Account\AccountInvoice;
use Illuminate\Support\Carbon;

/**
 * `account_invoices.status` has an `overdue` value in its enum that nothing in
 * GymHub ever writes — the codebase only ever sets pending, paid or void, and
 * overdue-ness is a property of the calendar, not a stored fact.
 *
 * So the contract's `overdue` is derived here. The line is the LOCK day rather
 * than the due day: an invoice past its due date but not yet past the lock day
 * is late in a way GymHub itself does not act on, and calling that "overdue"
 * in the console would put a red badge on accounts nothing is wrong with.
 */
class InvoiceStatusResolver
{
    /**
     * Days after the cycle start at which an unpaid invoice locks the account.
     */
    private const GRACE_DAYS = BillingCycleConstant::CYCLE_DAY_LOCK - BillingCycleConstant::CYCLE_DAY_DUE;

    /**
     * The invoice's status as the contract reports it.
     *
     * @param AccountInvoice $invoice
     *
     * @return string
     */
    public static function resolve(AccountInvoice $invoice): string
    {
        if ($invoice->status !== AccountInvoiceStatusConstant::STATUS_PENDING) {
            return $invoice->status;
        }

        return self::isOverdue($invoice) ? PlatformConstant::INVOICE_OVERDUE : PlatformConstant::INVOICE_PENDING;
    }

    /**
     * @param AccountInvoice $invoice
     *
     * @return bool
     */
    public static function isOverdue(AccountInvoice $invoice): bool
    {
        if ($invoice->status !== AccountInvoiceStatusConstant::STATUS_PENDING) {
            return false;
        }

        $dueDate = self::dueDate($invoice);

        return $dueDate !== null && $dueDate->isPast();
    }

    /**
     * When this invoice stops being merely unpaid.
     *
     * There is no `due_date` column. `period_from` is the billing cycle start
     * (the 5th, per BillingCycleConstant), so the lock day is that plus the
     * grace window. `invoice_date` is the fallback for older rows that predate
     * the period columns.
     *
     * @param AccountInvoice $invoice
     *
     * @return Carbon|null Null when the invoice carries no usable anchor date.
     */
    public static function dueDate(AccountInvoice $invoice): ?Carbon
    {
        $anchor = $invoice->period_from ?? $invoice->invoice_date;

        if ($anchor === null) {
            return null;
        }

        return Carbon::parse($anchor)->startOfDay()->addDays(self::GRACE_DAYS);
    }

    /**
     * The same rule in SQL, for filtering and summing without loading rows.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    public static function overdueSqlCondition(): array
    {
        $cutoff = Carbon::now()->startOfDay()->subDays(self::GRACE_DAYS)->toDateString();

        $sql = 'account_invoices.status = ?'
            .' AND COALESCE(account_invoices.period_from, account_invoices.invoice_date) IS NOT NULL'
            .' AND COALESCE(account_invoices.period_from, account_invoices.invoice_date) < ?';

        return [$sql, [AccountInvoiceStatusConstant::STATUS_PENDING, $cutoff]];
    }
}
