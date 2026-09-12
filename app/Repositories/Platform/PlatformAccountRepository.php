<?php

namespace App\Repositories\Platform;

use App\Constant\AccountInvoiceStatusConstant;
use App\Models\Account\Account;
use App\Support\Platform\AccountStatusResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Account reads for the platform contract.
 *
 * Every query here is deliberately unscoped by account — the console is a
 * cross-tenant operator tool, which is exactly why the service token guarding
 * it is the whole security boundary.
 */
class PlatformAccountRepository
{
    /**
     * Paginated account list with the derived status, owner and balance the
     * console's table renders.
     *
     * @param array<string, mixed> $filters status, search, createdFrom, createdTo
     * @param int $page
     * @param int $limit
     *
     * @return LengthAwarePaginator
     */
    public function paginateAccounts(array $filters, int $page, int $limit): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if (!empty($filters['status'])) {
            [$statusSql, $statusBindings] = AccountStatusResolver::sqlExpression();
            $query->whereRaw("({$statusSql}) = ?", [...$statusBindings, $filters['status']]);
        }

        if (!empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('accounts.account_name', 'like', $term)
                    ->orWhere('accounts.account_email', 'like', $term)
                    ->orWhere('accounts.billing_email', 'like', $term);
            });
        }

        if (!empty($filters['createdFrom'])) {
            $query->whereDate('accounts.created_at', '>=', $filters['createdFrom']);
        }

        if (!empty($filters['createdTo'])) {
            $query->whereDate('accounts.created_at', '<=', $filters['createdTo']);
        }

        return $query
            ->orderByDesc('accounts.created_at')
            ->paginate($limit, ['*'], 'page', $page);
    }

    /**
     * One account, carrying the same derived columns as the list.
     *
     * Its invoices and payment requests are fetched by their own repositories
     * rather than as relations here — `Account` declares neither, and adding
     * them for one consumer would widen a model the whole app shares.
     *
     * @param int $accountId
     *
     * @return Account|null
     */
    public function findAccountDetail(int $accountId): ?Account
    {
        return $this->baseQuery()->find($accountId);
    }

    /**
     * Does this account exist at all?
     *
     * Deliberately not `findAccountDetail() !== null`: that query carries four
     * correlated subqueries and an eager load, none of which an existence check
     * needs.
     *
     * @param int $accountId
     *
     * @return bool
     */
    public function accountExists(int $accountId): bool
    {
        return Account::query()->whereKey($accountId)->exists();
    }

    /**
     * The shared shape: latest subscription plan joined, plus the derived
     * columns the resources read. Selecting `accounts.*` keeps the model
     * hydrated normally; the extras arrive as plain attributes.
     *
     * @return Builder
     */
    private function baseQuery(): Builder
    {
        [$statusSql, $statusBindings] = AccountStatusResolver::sqlExpression();

        $query = Account::query()->select('accounts.*');
        AccountStatusResolver::latestPlanJoin($query);

        return $query
            ->selectRaw("({$statusSql}) as derived_status", $statusBindings)
            ->selectSub($this->outstandingSub(), 'outstanding_amount')
            ->selectSub($this->pendingInvoiceCountSub(), 'pending_invoice_count')
            ->selectSub($this->ownerSub("CONCAT(users.firstname, ' ', users.lastname)"), 'owner_name')
            ->selectSub($this->ownerSub('users.email'), 'owner_email')
            ->with(['activeAccountSubscriptionPlan.subscriptionPlan']);
    }

    /**
     * Unpaid balance: the sum of every invoice still pending. Void and paid
     * invoices are excluded, so this is what the subscriber actually owes.
     *
     * @return QueryBuilder
     */
    private function outstandingSub(): QueryBuilder
    {
        return DB::table('account_invoices')
            ->selectRaw('COALESCE(SUM(account_invoices.total_amount), 0)')
            ->whereColumn('account_invoices.account_id', 'accounts.id')
            ->where('account_invoices.status', AccountInvoiceStatusConstant::STATUS_PENDING);
    }

    /**
     * @return QueryBuilder
     */
    private function pendingInvoiceCountSub(): QueryBuilder
    {
        return DB::table('account_invoices')
            ->selectRaw('COUNT(*)')
            ->whereColumn('account_invoices.account_id', 'accounts.id')
            ->where('account_invoices.status', AccountInvoiceStatusConstant::STATUS_PENDING);
    }

    /**
     * The account owner's details, pulled as a subquery rather than a relation
     * so a page of accounts stays one query.
     *
     * @param string $expression Column or SQL expression to select from `users`.
     *
     * @return QueryBuilder
     */
    private function ownerSub(string $expression): QueryBuilder
    {
        return DB::table('users')
            ->selectRaw($expression)
            ->whereColumn('users.account_id', 'accounts.id')
            ->where('users.is_account_owner', true)
            ->whereNull('users.deleted_at')
            ->orderBy('users.id')
            ->limit(1);
    }
}
