<?php

namespace App\Services\Platform;

use App\Models\Account\AccountPaymentRequest;
use App\Repositories\Platform\PlatformPaymentRequestRepository;
use App\Services\Admin\AdminPaymentRequestService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Payment requests for the console: the list, and the two decisions.
 *
 * The decisions deliberately hold NO approval logic of their own. Approving a
 * receipt in GymHub marks an invoice paid, moves the coverage window, clears
 * `locked_at`, reactivates the account and fires referral evaluation — all
 * inside one transaction in AdminPaymentRequestService. A second
 * implementation of that for the console would drift from the artisan path
 * within a release, and the two would settle subscriptions differently.
 *
 * So this class does exactly two things the existing service does not: it
 * records WHICH console operator decided, and it turns the service's
 * exceptions into HTTP-shaped ones.
 */
class PlatformPaymentRequestService
{
    public function __construct(
        private PlatformPaymentRequestRepository $paymentRequestRepository,
        private AdminPaymentRequestService $adminPaymentRequestService,
        private PlatformReceiptService $receiptService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @param int $page
     * @param int $limit
     *
     * @return LengthAwarePaginator
     */
    public function getPaymentRequests(array $filters, int $page, int $limit): LengthAwarePaginator
    {
        $paginator = $this->paymentRequestRepository->paginatePaymentRequests($filters, $page, $limit);

        $this->receiptService->attachReceipts($paginator->getCollection());

        return $paginator;
    }

    /**
     * Approve a receipt on behalf of a console operator.
     *
     * `approved_by` stays null — it is a GymHub user id and a console operator
     * has none. The attribution is written first, inside the same transaction,
     * so a failed approval cannot leave the row claiming an actor who did not
     * succeed, while an approval can never land without one.
     *
     * @param int $requestId
     * @param string|null $actor
     *
     * @return AccountPaymentRequest
     *
     * @throws \InvalidArgumentException When the request is not pending.
     */
    public function approve(int $requestId, ?string $actor): AccountPaymentRequest
    {
        return DB::transaction(function () use ($requestId, $actor) {
            $this->recordActor($requestId, $actor);

            return $this->receiptService->attachReceipt(
                $this->reload($this->adminPaymentRequestService->approve($requestId, null)),
            );
        });
    }

    /**
     * Reject a receipt with a reason the subscriber receives.
     *
     * @param int $requestId
     * @param string $reason
     * @param string|null $actor
     *
     * @return AccountPaymentRequest
     *
     * @throws \InvalidArgumentException When the request is not pending.
     */
    public function reject(int $requestId, string $reason, ?string $actor): AccountPaymentRequest
    {
        return DB::transaction(function () use ($requestId, $reason, $actor) {
            $this->recordActor($requestId, $actor);

            return $this->receiptService->attachReceipt(
                $this->reload($this->adminPaymentRequestService->reject($requestId, null, $reason)),
            );
        });
    }

    /**
     * Stamp the deciding operator on the row before the decision is applied.
     *
     * @param int $requestId
     * @param string|null $actor
     *
     * @return void
     */
    private function recordActor(int $requestId, ?string $actor): void
    {
        if ($actor === null) {
            return;
        }

        $this->paymentRequestRepository->updateActor($requestId, $actor);
    }

    /**
     * Re-read the decided row through the platform query so the response
     * carries the same account name and requester the list does.
     *
     * @param AccountPaymentRequest $decided
     *
     * @return AccountPaymentRequest
     */
    private function reload(AccountPaymentRequest $decided): AccountPaymentRequest
    {
        return $this->paymentRequestRepository->findById($decided->id) ?? $decided;
    }
}
