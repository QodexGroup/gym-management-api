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
use App\Models\Account\AccountSubscriptionPlan;
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

            // Standalone requests store `payment_transaction` as a plain string (not a morph class).
            // Avoid eager-loading the morph relation for those, otherwise Eloquent will try to resolve
            // 'Reactivation Fee' as a model class and crash.
            $standaloneTypes = ['Reactivation Fee'];
            $request->load(['account']);
            if (!in_array($request->payment_transaction, $standaloneTypes, true)) {
                $request->load(['paymentTransaction']);
            }

            // Apply standalone / non-invoice approval side effects (reactivation and trial upgrades).
            switch ($request->payment_transaction) {
                case 'Reactivation Fee':
                    $this->processStandaloneReactivationFeeApproval($request);
                    break;
                case AccountSubscriptionPlan::class:
                    $this->processTrialUpgradeApproval($request);
                    break;
                case AccountInvoice::class:
                    $this->processInvoicePaymentApproval($request);
                    break;
                default:
                    // No-op here.
                    break;
            }

            $this->requestRepository->markAsApproved($request, $adminUserId);

            if (in_array($request->payment_transaction, $standaloneTypes, true)) {
                return $request->fresh(['account']);
            }

            return $request->fresh(['account', 'paymentTransaction']);
        });
    }

    /**
     * Approve a payment request that is linked to an AccountInvoice.
     * Marks the invoice paid and activates the subscription window for non-reactivation invoices.
     */
    private function processInvoicePaymentApproval(AccountPaymentRequest $request): void
    {
        if ($request->payment_transaction !== AccountInvoice::class || !$request->payment_transaction_id) {
            return;
        }

        $invoice = $this->invoiceRepository->findByIdWithRelations((int) $request->payment_transaction_id);
        if (!$invoice) {
            return;
        }

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

    /**
     * Approve standalone reactivation fee payments (no AccountInvoice).
     */
    private function processStandaloneReactivationFeeApproval(AccountPaymentRequest $request): void
    {
        $paymentDetails = $this->decodeJsonToArray($request->payment_details);
        if (($paymentDetails['reactivationProcessed'] ?? false) === true) {
            // Idempotency: already processed previously.
            return;
        }

        $accountId = (int) $request->account_id;
        $paidAt = Carbon::now()->copy()->startOfDay();

        $asp = $this->accountSubscriptionPlanRepository->findLatestByAccountIdWithPlan($accountId);
        if (!$asp || !$asp->subscriptionPlan || $asp->subscriptionPlan->is_trial) {
            throw new \RuntimeException('Cannot process reactivation: missing or trial ASP.');
        }

        $monthlyPlan = $this->subscriptionPlanRepository->findDefaultMonthlyPaidPlan();
        if (!$monthlyPlan) {
            throw new \RuntimeException('Default monthly subscription plan not configured.');
        }

        $nextCycleStart = $this->nextCycleStartAfterPayment($paidAt);

        // For reactivation, subscription ends after 1 full monthly interval + 1 free month.
        $subscriptionEndsAt = BillingLifecycleService::nextCycleStart(
            $nextCycleStart->copy(),
            AccountSubscriptionIntervalConstant::INTERVAL_MONTH
        )->addMonthNoOverflow();

        $this->accountSubscriptionPlanRepository->applyReactivationWindow(
            $asp,
            $monthlyPlan,
            $nextCycleStart,
            $subscriptionEndsAt
        );

        $this->accountRepository->activateAccountById($accountId);

        // No reactivation invoice id exists for standalone requests,
        // so void all pending subscription invoices for this account.
        $this->invoiceRepository->voidUnpaidByAccountIdExceptInvoice($accountId, 0);

        // Persist full reactivation metadata for client visibility/auditing.
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
    }

    /**
     * Approve trial upgrade payments linked to an AccountSubscriptionPlan ASP.
     */
    private function processTrialUpgradeApproval(AccountPaymentRequest $request): void
    {
        $paymentDetails = $this->decodeJsonToArray($request->payment_details);
        if (($paymentDetails['type'] ?? null) !== 'subscription_upgrade') {
            // Not the expected upgrade payload; just persist decoded details and exit.
            $this->requestRepository->updatePaymentDetails($request, $paymentDetails);
            return;
        }

        if (($paymentDetails['subscriptionUpgradeProcessed'] ?? false) === true) {
            // Idempotency: already processed previously.
            return;
        }

        $accountId = (int) $request->account_id;
        $subscriptionPlanId = (int) ($paymentDetails['subscriptionPlanId'] ?? 0);
        $requestedAtStr = (string) ($paymentDetails['requestedAt'] ?? '');

        if ($subscriptionPlanId <= 0) {
            throw new \RuntimeException('Subscription upgrade payment missing subscriptionPlanId.');
        }

        $requestedAt = $requestedAtStr ? Carbon::parse($requestedAtStr) : Carbon::now();

        /** @var AccountSubscriptionPlan|null $asp */
        $request->loadMissing(['paymentTransaction']);
        $asp = $request->paymentTransaction;
        if (!$asp) {
            throw new \RuntimeException('Missing ASP for subscription upgrade.');
        }
        $asp->loadMissing(['subscriptionPlan']);
        if (!$asp->subscriptionPlan) {
            throw new \RuntimeException('Missing ASP subscription plan for upgrade.');
        }

        $newPlan = $this->subscriptionPlanRepository->findById($subscriptionPlanId);
        if (!$newPlan || $newPlan->is_trial) {
            throw new \RuntimeException('Invalid subscription plan for upgrade.');
        }

        // If trial is still active when admin approves, start right after trial ends.
        // Otherwise, start at the time owner submitted the upgrade request.
        $trialEndsAt = $asp->trial_ends_at;
        if ($trialEndsAt && $trialEndsAt->isFuture()) {
            $subscriptionStartsAt = $trialEndsAt->copy()->addDay()->startOfDay();
        } else {
            $subscriptionStartsAt = $requestedAt->copy()->startOfDay();
        }

        $interval = $newPlan->interval ?? AccountSubscriptionIntervalConstant::INTERVAL_MONTH;
        $subscriptionEndsAtExclusive = match ($interval) {
            AccountSubscriptionIntervalConstant::INTERVAL_QUARTER => $subscriptionStartsAt->copy()->addMonthsNoOverflow(3),
            AccountSubscriptionIntervalConstant::INTERVAL_YEAR => $subscriptionStartsAt->copy()->addYears(1),
            default => $subscriptionStartsAt->copy()->addMonthNoOverflow(),
        };

        $this->accountSubscriptionPlanRepository->applyReactivationWindow(
            $asp,
            $newPlan,
            $subscriptionStartsAt,
            $subscriptionEndsAtExclusive
        );


        $this->accountRepository->activateAccountById($accountId);

        // Avoid double charges by voiding any pending subscription invoices (if generated).
        $this->invoiceRepository->voidUnpaidByAccountIdExceptInvoice($accountId, 0);

        $paymentDetails['subscriptionUpgradeProcessed'] = true;
        $paymentDetails['subscriptionUpgradeProcessedAt'] = Carbon::now()->toDateTimeString();
        $paymentDetails['subscription'] = [
            'subscriptionStartsAt' => $subscriptionStartsAt->toDateString(),
            // display end subtracts 1 day from exclusive end
            'subscriptionEndsAt' => $subscriptionEndsAtExclusive->copy()->subDay()->toDateString(),
            'interval' => $interval,
        ];

        $this->requestRepository->updatePaymentDetails($request, $paymentDetails);
    }

    /**
     * Ensure approved payment requests have had their side-effects applied.
     * This is intended for idempotent commands that can be re-run.
     */
    public function processApprovedIfNeeded(AccountPaymentRequest $request): void
    {
        switch ($request->payment_transaction) {
            case 'Reactivation Fee':
                $this->processStandaloneReactivationFeeApproval($request);
                break;

            case AccountSubscriptionPlan::class:
                $paymentDetails = $this->decodeJsonToArray($request->payment_details);
                if (($paymentDetails['type'] ?? null) === 'subscription_upgrade') {
                    $this->processTrialUpgradeApproval($request);
                }
                break;

            default:
                // No-op for other payment transactions.
                break;
        }
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

