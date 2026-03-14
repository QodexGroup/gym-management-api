<?php

namespace App\Services\Account\AccountSubscription;

use App\Constant\AccountFeeConstant;
use App\Constant\AccountInvoiceStatusConstant;
use App\Constant\BillingCycleConstant;
use App\Helpers\GenericData;
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

class AccountPaymentRequestService
{
    public function __construct(
        private AccountPaymentRequestRepository $requestRepository,
        private AccountInvoiceRepository $invoiceRepository,
        private AccountRepository $accountRepository,
        private AccountSubscriptionPlanRepository $accountSubscriptionPlanRepository,
        private SubscriptionPlanRepository $subscriptionPlanRepository,
    ) {
    }

    /**
     * Create payment request for an invoice.
     *
     * @param GenericData $genericData
     *
     * @return AccountPaymentRequest
     */
    public function createInvoicePaymentRequest(GenericData $genericData): AccountPaymentRequest
    {
        return DB::transaction(function () use ($genericData) {
            $data = $genericData->getData();
            $accountId = $genericData->userData->account_id;
            $invoiceId = (int) $data->invoiceId;

            $invoice = $this->invoiceRepository->findByIdWithRelations($invoiceId);
            if (!$invoice) {
                throw new \Exception('Invoice not found.');
            }

            if ($invoice->account_id !== $accountId) {
                throw new \Exception('Invoice does not belong to your account.');
            }

            if ($invoice->status === AccountInvoiceStatusConstant::STATUS_PAID) {
                throw new \Exception('Invoice is already paid.');
            }

            if ($this->requestRepository->hasPendingForAccount($accountId, AccountInvoice::class, $invoiceId)) {
                throw new \Exception('You already have a pending payment request for this invoice. Please wait for approval.');
            }

            // Receipt file is uploaded and stored by the frontend (e.g. Firebase).
            $request = $this->requestRepository->createInvoicePaymentRequest($genericData, $invoice);

            return $request->load(['account', 'paymentTransaction']);
        });
    }

    /**
     * Create a standalone reactivation fee payment request.
     *
     * @param GenericData $genericData
     *
     * @return AccountPaymentRequest
     */
    public function createReactivationPaymentRequest(GenericData $genericData): AccountPaymentRequest
    {
        return DB::transaction(function () use ($genericData) {
            $accountId = $genericData->userData->account_id;

            if ($this->requestRepository->hasPendingForAccount($accountId, 'Reactivation Fee', null)) {
                throw new \Exception('You already have a pending reactivation payment request. Please wait for approval.');
            }

            $request = $this->requestRepository->createReactivationPaymentRequest($genericData, AccountFeeConstant::REACTIVATION_FEE_AMOUNT);

            return $request->load(['account']);
        });
    }

    /**
     * Update subscription plan selection (takes effect on next billing cycle).
     * No invoice or payment request is created - just updates the plan selection.
     *
     * @param GenericData $genericData
     *
     * @return array{message: string, nextBillingDate: string|null}
     */
    public function createSubscriptionRequest(GenericData $genericData): array
    {
        return DB::transaction(function () use ($genericData) {
            $data = $genericData->getData();
            $accountId = $genericData->userData->account_id;
            $subscriptionPlanId = (int) $data->subscriptionPlanId;

            // Get account with active subscription plan
            $account = $this->accountRepository->findAccountWithRelations($accountId);
            if (!$account) {
                throw new \Exception('Account not found.');
            }

            $asp = $account->activeAccountSubscriptionPlan;
            if (!$asp) {
                throw new \Exception('No active subscription plan found.');
            }

            // Get new plan
            $newPlan = $this->subscriptionPlanRepository->findPaidPlanById($subscriptionPlanId);
            if (!$newPlan) {
                throw new \Exception('Subscription plan not found or is a trial plan.');
            }

            // Check if plan is already selected
            if ($asp->subscription_plan_id === $newPlan->id) {
                throw new \Exception('You are already subscribed to this plan.');
            }

            // Get current plan before updating (needed for interval calculation)
            $currentPlan = $asp->subscriptionPlan;
            $subscriptionEndsAt = $asp->subscription_ends_at;
            $subscriptionStartsAt = $asp->subscription_starts_at;

            // Update the plan selection via repository
            $this->accountSubscriptionPlanRepository->updatePlanSelection($asp, $newPlan);

            // Determine when the change takes effect and build message
            $message = "Plan updated successfully. ";
            $nextBillingDate = null;

            if ($subscriptionStartsAt === null) {
                // Still in trial or not activated - change takes effect after payment approval
                $message .= "Your subscription will start with the {$newPlan->name} plan after payment approval.";
            } else {
                // Active subscription - change takes effect on next billing cycle
                // Calculate next billing cycle start based on current subscription end date
                $endDate = $subscriptionEndsAt ?? Carbon::now();
                $currentInterval = $currentPlan->interval ?? 'month';

                $nextCycleStart = BillingLifecycleService::nextCycleStart(
                    $endDate->copy(),
                    $currentInterval
                );

                $nextBillingDate = $nextCycleStart->format('M d, Y');
                $message .= "Your next billing cycle (starting {$nextBillingDate}) will be for the {$newPlan->name} plan.";
            }

            return [
                'message' => $message,
                'nextBillingDate' => $nextBillingDate,
            ];
        });
    }

}
