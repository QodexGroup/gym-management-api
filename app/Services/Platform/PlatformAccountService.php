<?php

namespace App\Services\Platform;

use App\Models\Account\Account;
use App\Repositories\Platform\PlatformAccountRepository;
use App\Repositories\Platform\PlatformInvoiceRepository;
use App\Repositories\Platform\PlatformPaymentRequestRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Account reads for the console.
 */
class PlatformAccountService
{
    public function __construct(
        private PlatformAccountRepository $accountRepository,
        private PlatformInvoiceRepository $invoiceRepository,
        private PlatformPaymentRequestRepository $paymentRequestRepository,
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
    public function getAccounts(array $filters, int $page, int $limit): LengthAwarePaginator
    {
        return $this->accountRepository->paginateAccounts($filters, $page, $limit);
    }

    /**
     * One account with its invoices and payment history attached.
     *
     * The two collections are hung off the model as plain attributes rather
     * than relations, because `Account` declares neither and the platform
     * surface should not widen a model the rest of the app shares.
     *
     * @param int $accountId
     *
     * @return Account|null
     */
    public function getAccountDetail(int $accountId): ?Account
    {
        $account = $this->accountRepository->findAccountDetail($accountId);

        if ($account === null) {
            return null;
        }

        $account->platform_invoices = $this->invoiceRepository->getInvoicesForAccount($accountId);
        $account->platform_payment_requests = $this->receiptService->attachReceipts(
            $this->paymentRequestRepository->getRequestsForAccount($accountId),
        );

        return $account;
    }
}
