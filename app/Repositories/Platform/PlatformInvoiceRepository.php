<?php

namespace App\Repositories\Platform;

use App\Data\Platform\PlatformInvoiceFilter;
use App\Models\Account\AccountInvoice;
use App\Support\Platform\InvoiceStatusResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Invoice reads for the platform contract.
 */
class PlatformInvoiceRepository
{
    /**
     * One page of invoices, newest first.
     *
     * Takes criteria, not the contract's status token: what `overdue` and
     * `pending` mean is decided in PlatformInvoiceService. This only knows how
     * to express the overdue rule in SQL, and whether it was asked for.
     *
     * @param PlatformInvoiceFilter $filter
     * @param int $page
     * @param int $limit
     *
     * @return LengthAwarePaginator
     */
    public function paginateInvoices(PlatformInvoiceFilter $filter, int $page, int $limit): LengthAwarePaginator
    {
        [$overdueSql, $overdueBindings] = InvoiceStatusResolver::overdueSqlCondition();

        return $this->baseQuery()
            ->when($filter->status !== null, fn (Builder $q) => $q->where('account_invoices.status', $filter->status))
            ->when($filter->onlyOverdue, fn (Builder $q) => $q->whereRaw($overdueSql, $overdueBindings))
            ->when($filter->excludeOverdue, fn (Builder $q) => $q->whereRaw("NOT ({$overdueSql})", $overdueBindings))
            ->when($filter->accountId !== null, fn (Builder $q) => $q->where('account_invoices.account_id', $filter->accountId))
            ->orderByDesc('account_invoices.id')
            ->paginate($limit, ['*'], 'page', $page);
    }

    /**
     * Recent invoices for one account, for the detail drill-down.
     *
     * @param int $accountId
     * @param int $limit
     *
     * @return Collection<int, AccountInvoice>
     */
    public function getInvoicesForAccount(int $accountId, int $limit = 50): Collection
    {
        return $this->baseQuery()
            ->where('account_invoices.account_id', $accountId)
            ->orderByDesc('account_invoices.id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Builder
     */
    private function baseQuery(): Builder
    {
        return AccountInvoice::query()
            ->select('account_invoices.*')
            ->selectSub(
                DB::table('accounts')
                    ->selectRaw('accounts.account_name')
                    ->whereColumn('accounts.id', 'account_invoices.account_id')
                    ->limit(1),
                'account_name',
            );
    }
}
