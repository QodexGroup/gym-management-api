<?php

namespace App\Services\Admin;

use App\Constant\AccountInvoiceTypeConstant;
use App\Constant\AccountInvoiceStatusConstant;
use App\Constant\AccountPaymentRequestStatusConstant;
use App\Constant\AccountStatusConstant;
use App\Constant\AccountSubscriptionIntervalConstant;
use App\Constant\BillingCycleConstant;
use App\Models\Account\AccountInvoice;
use App\Models\Account\AccountPaymentRequest;
use App\Repositories\Account\AccountRepository;
use App\Repositories\Account\AccountSubscription\AccountInvoiceRepository;
use App\Repositories\Account\AccountSubscription\AccountPaymentRequestRepository;
use App\Repositories\Account\AccountSubscription\AccountSubscriptionPlanRepository;
use App\Repositories\Account\AccountSubscription\SubscriptionPlanRepository;
use App\Services\Account\AccountSubscription\BillingLifecycleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminPaymentRequestService
{
    public function __construct(
        private AccountPaymentRequestRepository $requestRepository,
        private AccountInvoiceRepository $invoiceRepository,
        private AccountSubscriptionPlanRepository $accountSubscriptionPlanRepository,
        private AccountRepository $accountRepository,
        private SubscriptionPlanRepository $subscriptionPlanRepository,
    ) {
    }

    /**
     * Approve payment request and mark invoice as paid.
     *
     * @param int $requestId
     * @param int|null $adminUserId
     *
     * @return AccountPaymentRequest
     */
    public function approve(int $requestId, ?int $adminUserId = null): AccountPaymentRequest
    {
        return DB::transaction(function () use ($requestId, $adminUserId) {
            $request = $this->requestRepository->findPendingById($requestId);
            if (!$request) {
                throw new \InvalidArgumentException('Pending payment request not found.');
            }

            $request->load(['account', 'paymentTransaction']);

            // Handle invoice payment
            if ($request->payment_transaction === AccountInvoice::class && $request->payment_transaction_id) {
                $invoice = $this->invoiceRepository->findByIdWithRelations((int) $request->payment_transaction_id);
                if ($invoice) {
                    $this->invoiceRepository->markAsPaid($invoice);

                    // Activation for normal subscription invoices.
                    // Reactivation fee invoices are processed by the dedicated reactivation command.
                    if (!$this->isReactivationFeeInvoice($invoice)) {
                        $asp = $invoice->accountSubscriptionPlan;
                        if ($asp && $asp->subscriptionPlan && !$asp->subscriptionPlan->is_trial) {
                            $this->accountSubscriptionPlanRepository->activatePaidSubscriptionPlan($asp);
                            $this->accountRepository->activateAccountById((int) $request->account_id);
                        }
                    }
                }
            }

            $this->requestRepository->markAsApproved($request, $adminUserId);

            return $request->fresh(['account', 'paymentTransaction']);
        });
    }

    /**
     * Process approved reactivation fee payment requests.
     * Applies free month to all paid plans (non-trial), voids old unpaid invoices, and reactivates account.
     *
     * @param int|null $accountId
     * @param int $limit
     *
     * @return int
     */
    public function processApprovedReactivations(?int $accountId = null, int $limit = 200): int
    {
        $processedCount = 0;
        $approvedRequests = $this->requestRepository->getApprovedInvoiceRequests($accountId, $limit);

        foreach ($approvedRequests as $request) {
            $paymentDetails = $this->decodeJsonToArray($request->payment_details);
            if (($paymentDetails['reactivationProcessed'] ?? false) === true) {
                continue;
            }

            if (!$request->payment_transaction_id) {
                continue;
            }

            $invoice = $this->invoiceRepository->findByIdWithRelations((int) $request->payment_transaction_id);
            if (!$invoice || !$this->isReactivationFeeInvoice($invoice)) {
                continue;
            }

            $result = DB::transaction(function () use ($request, $paymentDetails, $invoice) {
                $asp = $this->accountSubscriptionPlanRepository->findLatestByAccountIdWithPlan((int) $request->account_id);
                if (!$asp || !$asp->subscriptionPlan || $asp->subscriptionPlan->is_trial) {
                    return false;
                }

                // On reactivation, always switch to the default monthly paid plan.
                $monthlyPlan = $this->subscriptionPlanRepository->findDefaultMonthlyPaidPlan();
                if (!$monthlyPlan) {
                    throw new \RuntimeException('Default monthly subscription plan not configured.');
                }

                $paidAt = ($request->approved_at ? $request->approved_at->copy() : Carbon::now())->startOfDay();
                $nextCycleStart = $this->nextCycleStartAfterPayment($paidAt);

                // Free month applies to monthly plan: one full month interval + one free month.
                $subscriptionEndsAt = BillingLifecycleService::nextCycleStart(
                    $nextCycleStart->copy(),
                    AccountSubscriptionIntervalConstant::INTERVAL_MONTH
                )->addMonthNoOverflow();

                // For reactivation, keep original trial dates, but switch to monthly plan and
                // adjust subscription window + unlock.
                $this->accountSubscriptionPlanRepository->applyReactivationWindow(
                    $asp,
                    $monthlyPlan,
                    $nextCycleStart,
                    $subscriptionEndsAt
                );

                $this->accountRepository->activateAccountById((int) $request->account_id);
                $this->invoiceRepository->voidUnpaidByAccountIdExceptInvoice((int) $request->account_id, (int) $invoice->id);

                $paymentDetails['reactivationProcessed'] = true;
                $paymentDetails['reactivationProcessedAt'] = Carbon::now()->toDateTimeString();
                $paymentDetails['reactivation'] = [
                    'paidAt' => $paidAt->toDateString(),
                    'prorateFrom' => $paidAt->toDateString(),
                    'prorateTo' => $nextCycleStart->copy()->subDay()->toDateString(),
                    'subscriptionStartsAt' => $nextCycleStart->toDateString(),
                    'subscriptionEndsAt' => $subscriptionEndsAt->copy()->subDay()->toDateString(),
                    'freeMonthApplied' => true,
                    'invoiceType' => AccountInvoiceTypeConstant::TYPE_REACTIVATION_FEE,
                ];

                $this->requestRepository->updatePaymentDetails($request, $paymentDetails);

                return true;
            });

            if ($result) {
                $processedCount++;
            }
        }

        return $processedCount;
    }

    /**
     * Reject payment request.
     *
     * @param int $requestId
     * @param int|null $adminUserId
     * @param string|null $reason
     *
     * @return AccountPaymentRequest
     */
    public function reject(int $requestId, ?int $adminUserId = null, ?string $reason = null): AccountPaymentRequest
    {
        $request = $this->requestRepository->findPendingById($requestId);
        if (!$request) {
            throw new \InvalidArgumentException('Pending payment request not found.');
        }

        $this->requestRepository->markAsRejected($request, $adminUserId, $reason);

        return $request->fresh(['account', 'paymentTransaction']);
    }

    /**
     * @param Carbon $paidAt
     *
     * @return Carbon
     */
    private function nextCycleStartAfterPayment(Carbon $paidAt): Carbon
    {
        $nextCycleStart = $paidAt->copy();
        if ((int) $paidAt->day >= BillingCycleConstant::CYCLE_DAY_DUE) {
            $nextCycleStart->addMonthNoOverflow();
        }

        return $nextCycleStart->day(BillingCycleConstant::CYCLE_DAY_DUE)->startOfDay();
    }

    /**
     * @param AccountInvoice $invoice
     *
     * @return bool
     */
    private function isReactivationFeeInvoice(AccountInvoice $invoice): bool
    {
        $details = $this->decodeJsonToArray($invoice->invoice_details);
        return ($details['invoiceType'] ?? null) === AccountInvoiceTypeConstant::TYPE_REACTIVATION_FEE;
    }

    /**
     *
     * @param string|null $json
     *
     * @return array
     */
    private function decodeJsonToArray(?string $json): array
    {
        if (!$json) {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

