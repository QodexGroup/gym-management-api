<?php

namespace App\Repositories\Platform;

use App\Models\Account\AccountPaymentRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Payment request reads for the platform contract.
 *
 * `paymentTransaction` is never eager-loaded here. It is a MorphTo whose type
 * column holds the literal string 'Reactivation Fee' for standalone
 * reactivation rows — not a class name — and resolving it throws. The contract
 * exposes a translated `paymentTransaction` kind instead, so the relation is
 * not needed.
 */
class PlatformPaymentRequestRepository
{
    /**
     * @param array<string, mixed> $filters status, accountId
     * @param int $page
     * @param int $limit
     *
     * @return LengthAwarePaginator
     */
    public function paginatePaymentRequests(array $filters, int $page, int $limit): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->when(!empty($filters['status']), fn (Builder $q) => $q->where('account_payment_requests.status', $filters['status']))
            ->when(!empty($filters['accountId']), fn (Builder $q) => $q->where('account_payment_requests.account_id', $filters['accountId']))
            ->orderByDesc('account_payment_requests.id')
            ->paginate($limit, ['*'], 'page', $page);
    }

    /**
     * Recent payment requests for one account, for the detail drill-down.
     *
     * @param int $accountId
     * @param int $limit
     *
     * @return Collection<int, AccountPaymentRequest>
     */
    public function getRequestsForAccount(int $accountId, int $limit = 50): Collection
    {
        return $this->baseQuery()
            ->where('account_payment_requests.account_id', $accountId)
            ->orderByDesc('account_payment_requests.id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param int $requestId
     *
     * @return AccountPaymentRequest|null
     */
    public function findById(int $requestId): ?AccountPaymentRequest
    {
        return $this->baseQuery()->find($requestId);
    }

    /**
     * Write the console operator who decided this request.
     *
     * `approved_by` is deliberately untouched — that column holds a GymHub user
     * id and a console operator has none.
     *
     * @param int $requestId
     * @param string $actor
     *
     * @return void
     */
    public function updateActor(int $requestId, string $actor): void
    {
        AccountPaymentRequest::whereKey($requestId)->update(['platform_actor' => $actor]);
    }

    /**
     * Adds the account name and the requester's name the console shows beside
     * every row, without a relation load per row.
     *
     * @return Builder
     */
    private function baseQuery(): Builder
    {
        return AccountPaymentRequest::query()
            ->select('account_payment_requests.*')
            ->selectSub(
                DB::table('accounts')
                    ->selectRaw('accounts.account_name')
                    ->whereColumn('accounts.id', 'account_payment_requests.account_id')
                    ->limit(1),
                'account_name',
            )
            ->selectSub(
                DB::table('users')
                    ->selectRaw("CONCAT(users.firstname, ' ', users.lastname)")
                    ->whereColumn('users.id', 'account_payment_requests.requested_by')
                    ->limit(1),
                'requested_by_name',
            );
    }
}
