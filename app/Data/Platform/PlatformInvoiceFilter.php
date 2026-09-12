<?php

namespace App\Data\Platform;

/**
 * Resolved criteria for the invoice list query.
 *
 * The contract's `status` is not a column value: `overdue` is a date rule no
 * row ever stores, and `pending` means unpaid AND not yet past the lock day.
 * PlatformInvoiceService decides what the token means and fills these in, so
 * the repository only has to apply them.
 */
class PlatformInvoiceFilter
{
    /**
     * The stored `account_invoices.status` to match, or null to not filter on
     * the column at all.
     */
    public ?string $status = null;

    /**
     * Keep only invoices past the lock day.
     */
    public bool $onlyOverdue = false;

    /**
     * Keep only invoices that have NOT reached the lock day. Paired with
     * $status so the pending and overdue filters partition the unpaid set
     * instead of overlapping.
     */
    public bool $excludeOverdue = false;

    /**
     * Narrow to one account, or null for every account.
     */
    public ?int $accountId = null;
}
