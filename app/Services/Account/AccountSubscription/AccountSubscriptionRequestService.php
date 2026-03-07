<?php

namespace App\Services\Account\AccountSubscription;

use App\Constant\AccountSubscriptionRequestConstant;
use App\Constant\AccountSubscriptionStatusConstant;
use App\Helpers\GenericData;
use App\Models\Account;
use App\Models\Account\AccountInvoice;
use App\Models\Account\AccountSubscriptionPlan;
use App\Models\Account\AccountSubscriptionRequest;
use App\Repositories\Account\AccountSubscription\AccountSubscriptionRequestRepository;
use App\Repositories\Account\AccountSubscription\PlatformSubscriptionPlanRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccountSubscriptionRequestService
{
    public function __construct(
        private AccountSubscriptionRequestRepository $requestRepository,
        private PlatformSubscriptionPlanRepository $planRepository,
        private BillingLifecycleService $billingLifecycle
    ) {
    }

    public function createRequest(GenericData $genericData): AccountSubscriptionRequest
    {
        $data = $genericData->getData();
        $accountId = $genericData->userData->account_id;
        $receiptUrl = $this->normalizeReceiptUrlForStorage((string) $data->receiptUrl, (int) $accountId);

        $plan = $this->planRepository->findPaidPlanById((int) $data->subscriptionPlanId);
        if (!$plan) {
            throw new \InvalidArgumentException('Subscription plan not found or is not a paid plan.');
        }

        $account = Account::with('activeAccountSubscriptionPlan')->findOrFail($accountId);
        $asp = $account->activeAccountSubscriptionPlan;
        if ($account->subscription_status === AccountSubscriptionStatusConstant::STATUS_ACTIVE
            && $asp && $asp->subscription_ends_at && $asp->subscription_ends_at->isFuture()) {
            throw new \Exception(AccountSubscriptionRequestConstant::MESSAGE_ALREADY_ACTIVE);
        }

        if ($this->requestRepository->hasPendingForAccount($accountId)) {
            throw new \Exception(AccountSubscriptionRequestConstant::MESSAGE_PENDING_EXISTS);
        }

        return DB::transaction(function () use ($accountId, $data, $plan, $genericData, $receiptUrl) {
            $asp = AccountSubscriptionPlan::firstOrCreate(
                [
                    'account_id' => $accountId,
                    'platform_subscription_plan_id' => $plan->id,
                ],
                [
                    'subscription_starts_at' => null,
                    'subscription_ends_at' => null,
                ]
            );

            $billingPeriod = BillingLifecycleService::billingPeriodForDate(Carbon::now());
            $invoice = $this->billingLifecycle->generateInvoiceForPeriod($asp, $billingPeriod);
            if (!$invoice) {
                $invoiceNumber = $this->billingLifecycle->nextInvoiceNumber();
                $cycleStart = Carbon::now()->day(BillingLifecycleService::CYCLE_DAY_DUE)->startOfDay();
                $invoice = AccountInvoice::create([
                    'account_id' => $accountId,
                    'account_subscription_plan_id' => $asp->id,
                    'invoice_number' => $invoiceNumber,
                    'billing_period' => $billingPeriod,
                    'plan_name' => $plan->name,
                    'plan_interval' => $plan->interval,
                    'plan_price' => $plan->price,
                    'billing_cycle_start_at' => $cycleStart,
                    'status' => AccountInvoice::STATUS_ISSUED,
                    'invoice_details' => [],
                ]);
            }

            $request = $this->requestRepository->create([
                'account_id' => $accountId,
                'account_invoice_id' => $invoice->id,
                'receipt_url' => $receiptUrl,
                'receipt_file_name' => $data->receiptFileName ?? null,
                'status' => AccountSubscriptionRequestConstant::STATUS_PENDING,
                'requested_by' => $genericData->userData->id,
            ]);

            return $request->load(['account', 'invoice.accountSubscriptionPlan.platformPlan']);
        });
    }

    public function approve(int $requestId, ?int $adminUserId = null): AccountSubscriptionRequest
    {
        return DB::transaction(function () use ($requestId, $adminUserId) {
            $request = $this->requestRepository->findPendingById($requestId);
            if (!$request) {
                throw new \InvalidArgumentException('Pending subscription request not found.');
            }
            $request->load(['account', 'invoice.accountSubscriptionPlan.platformPlan']);
            $invoice = $request->invoice;
            $asp = $invoice->accountSubscriptionPlan;
            $plan = $asp->platformPlan;
            $account = $request->account;

            $now = Carbon::now();
            $cycleStart = $now->copy()->day(BillingLifecycleService::CYCLE_DAY_DUE)->startOfDay();
            if ($now->day < BillingLifecycleService::CYCLE_DAY_DUE) {
                $cycleStart->subMonth();
            }
            $subscriptionEndsAt = BillingLifecycleService::nextCycleStart($cycleStart->copy(), $plan->interval ?? 'month');

            $asp->update([
                'subscription_starts_at' => $cycleStart,
                'subscription_ends_at' => $subscriptionEndsAt,
                'trial_starts_at' => null,
                'trial_ends_at' => null,
            ]);

            $account->update([
                'subscription_status' => AccountSubscriptionStatusConstant::STATUS_ACTIVE,
            ]);

            $invoice->update(['status' => AccountInvoice::STATUS_PAID]);

            $request->update([
                'status' => AccountSubscriptionRequestConstant::STATUS_APPROVED,
                'approved_by' => $adminUserId,
                'approved_at' => $now,
            ]);

            return $request->fresh(['account', 'invoice.accountSubscriptionPlan.platformPlan']);
        });
    }

    public function reject(int $requestId, ?int $adminUserId = null, ?string $reason = null): AccountSubscriptionRequest
    {
        $request = $this->requestRepository->findPendingById($requestId);
        if (!$request) {
            throw new \InvalidArgumentException('Pending subscription request not found.');
        }

        $request->update([
            'status' => AccountSubscriptionRequestConstant::STATUS_REJECTED,
            'approved_by' => $adminUserId,
            'approved_at' => Carbon::now(),
            'rejection_reason' => $reason,
        ]);

        return $request->fresh(['account', 'invoice.accountSubscriptionPlan.platformPlan']);
    }

    private function normalizeReceiptUrlForStorage(string $receiptUrl, int $accountId): string
    {
        $trimmedReceiptUrl = trim($receiptUrl);
        if ($trimmedReceiptUrl === '') {
            return $trimmedReceiptUrl;
        }

        if (str_starts_with($trimmedReceiptUrl, 'http://') || str_starts_with($trimmedReceiptUrl, 'https://')) {
            $path = parse_url($trimmedReceiptUrl, PHP_URL_PATH);
            if (is_string($path) && str_contains($path, '/o/')) {
                $encodedStoragePath = explode('/o/', $path, 2)[1] ?? '';
                if ($encodedStoragePath !== '') {
                    $decodedStoragePath = urldecode($encodedStoragePath);
                    return $this->syncReceiptPathAccountId($decodedStoragePath, $accountId);
                }
            }
        }

        return $this->syncReceiptPathAccountId($trimmedReceiptUrl, $accountId);
    }

    private function syncReceiptPathAccountId(string $storagePath, int $accountId): string
    {
        $normalizedPath = ltrim(trim($storagePath), '/');
        if ($normalizedPath === '') {
            return $normalizedPath;
        }

        $pathSegments = explode('/', $normalizedPath);
        if (count($pathSegments) > 0 && ctype_digit($pathSegments[0])) {
            $pathSegments[0] = (string) $accountId;
            return implode('/', $pathSegments);
        }

        return $accountId . '/' . $normalizedPath;
    }
}
