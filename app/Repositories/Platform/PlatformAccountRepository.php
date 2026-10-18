<?php

namespace App\Repositories\Platform;

use App\Constant\AccountInvoiceStatusConstant;
use App\Constant\PlatformConstant;
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
     * @param array<string, mixed> $filters status, search, createdFrom, createdTo, sort
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

        return $this->applySort($query, $filters['sort'] ?? null)
            ->paginate($limit, ['*'], 'page', $page);
    }

    /**
     * Order the list by one of the contract's sort keys.
     *
     * Coverage end is the expiry the console chases, so it ascends — soonest
     * (and already past) first. It reads through COALESCE because a trial
     * account has no `subscription_ends_at` yet and its trial end IS its
     * coverage end; ordering on the raw column alone would bunch every trial
     * at one end on a NULL. Accounts with no plan row at all have no coverage
     * to chase and sort last rather than leading the list.
     *
     * Anything else, including no sort at all, keeps the default: newest
     * signups first.
     *
     * @param Builder $query
     * @param string|null $sort
     *
     * @return Builder
     */
    private function applySort(Builder $query, ?string $sort): Builder
    {
        $coverageEnd = 'COALESCE(asp.subscription_ends_at, asp.trial_ends_at)';

        return match ($sort) {
            PlatformConstant::ACCOUNT_SORT_COVERAGE_ENDS_AT => $query
                ->orderByRaw("{$coverageEnd} IS NULL")
                ->orderByRaw("{$coverageEnd} ASC"),
            default => $query->orderByDesc('accounts.created_at'),
        };
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
