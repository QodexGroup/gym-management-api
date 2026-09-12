<?php

namespace App\Services\Platform;

use App\Constant\AccountInvoiceStatusConstant;
use App\Constant\PlatformConstant;
use App\Data\Platform\PlatformInvoiceFilter;
use App\Repositories\Platform\PlatformInvoiceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Invoices as the console reads them.
 *
 * This class exists for one reason: the contract's status vocabulary does not
 * line up with the column. Two of its four values are rules rather than stored
 * facts, and deciding what they mean is business logic — so it happens here,
 * and the repository receives criteria it can apply without knowing why.
 */
class PlatformInvoiceService
{
    public function __construct(
        private PlatformInvoiceRepository $invoiceRepository,
    ) {
    }

    /**
     * One page of invoices across every account, newest first.
     *
     * @param array<string, mixed> $filters status, accountId
     * @param int $page
     * @param int $limit
     *
     * @return LengthAwarePaginator
     */
    public function getInvoices(array $filters, int $page, int $limit): LengthAwarePaginator
    {
        $invoiceFilter = $this->resolveFilter($filters);

        return $this->invoiceRepository->paginateInvoices($invoiceFilter, $page, $limit);
    }

    /**
     * Translate the contract's status token into criteria the query can apply.
     *
     * No row is ever stored as `overdue` — it is a comparison against the
     * billing calendar (see InvoiceStatusResolver). `pending` is therefore
     * narrowed to unpaid invoices that have NOT reached the lock day, so that
     * the two filters partition the unpaid set instead of overlapping. Any
     * other value is a real column value and matches it directly.
     *
     * @param array<string, mixed> $filters
     *
     * @return PlatformInvoiceFilter
     */
    private function resolveFilter(array $filters): PlatformInvoiceFilter
    {
        $status = $filters['status'] ?? null;

        $invoiceFilter = new PlatformInvoiceFilter();
        $invoiceFilter->accountId = empty($filters['accountId']) ? null : (int) $filters['accountId'];

        if ($status === PlatformConstant::INVOICE_OVERDUE) {
            $invoiceFilter->onlyOverdue = true;

            return $invoiceFilter;
        }

        if ($status === PlatformConstant::INVOICE_PENDING) {
            $invoiceFilter->status = AccountInvoiceStatusConstant::STATUS_PENDING;
            $invoiceFilter->excludeOverdue = true;

            return $invoiceFilter;
        }

        if (!empty($status)) {
            $invoiceFilter->status = $status;
        }

        return $invoiceFilter;
    }
}
