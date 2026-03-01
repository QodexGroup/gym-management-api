<?php

namespace App\Services\Account\AccountSubscription;

// TODO (production): Onboarding fee — add one-time onboarding fee flow (computation/charging) when going live.
use App\Constant\AccountSubscriptionIntervalConstant;
use App\Constant\AccountSubscriptionRequestConstant;
use App\Constant\AccountSubscriptionStatusConstant;
use App\Helpers\GenericData;
use App\Models\Account;
use App\Models\Account\AccountSubscriptionRequest;
use App\Repositories\Account\AccountSubscription\AccountSubscriptionRequestRepository;
use App\Repositories\Account\AccountSubscription\PlatformSubscriptionPlanRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccountSubscriptionRequestService
{
    public function __construct(
        private AccountSubscriptionRequestRepository $requestRepository,
        private PlatformSubscriptionPlanRepository $planRepository
    ) {
    }

    public function createRequest(GenericData $genericData): AccountSubscriptionRequest
    {
        $data = $genericData->getData();
        $accountId = $genericData->userData->account_id;

        $plan = $this->planRepository->findPaidPlanById((int) $data->subscriptionPlanId);
        if (! $plan) {
            throw new \InvalidArgumentException('Subscription plan not found or is not a paid plan.');
        }

        $account = Account::findOrFail($accountId);
        $currentPeriodEnds = $account->current_period_ends_at;
        if ($account->subscription_status === AccountSubscriptionStatusConstant::STATUS_ACTIVE
            && $currentPeriodEnds
            && $currentPeriodEnds->isFuture()) {
            throw new \Exception(AccountSubscriptionRequestConstant::MESSAGE_ALREADY_ACTIVE);
        }

        if ($this->requestRepository->hasPendingForAccount($accountId)) {
            throw new \Exception(AccountSubscriptionRequestConstant::MESSAGE_PENDING_EXISTS);
        }

        return $this->requestRepository->create([
            'account_id' => $accountId,
            'subscription_plan_id' => $plan->id,
            'receipt_url' => $data->receiptUrl,
            'receipt_file_name' => $data->receiptFileName ?? null,
            'status' => AccountSubscriptionRequestConstant::STATUS_PENDING,
            'requested_by' => $genericData->userData->id,
        ]);
    }

    public function approve(int $requestId, ?int $adminUserId = null): AccountSubscriptionRequest
    {
        return DB::transaction(function () use ($requestId, $adminUserId) {
            $request = $this->requestRepository->findPendingById($requestId);
            if (! $request) {
                throw new \InvalidArgumentException('Pending subscription request not found.');
            }
            $request->load(['account', 'subscriptionPlan']);
            $plan = $request->subscriptionPlan;
            $account = $request->account;

            $now = Carbon::now();
            $periodEndsAt = $this->calculatePeriodEnd($now, $plan->interval);

            $account->update([
                'subscription_status' => AccountSubscriptionStatusConstant::STATUS_ACTIVE,
                'subscription_plan_id' => $plan->id,
                'current_period_ends_at' => $periodEndsAt,
                'trial_ends_at' => null,
            ]);

            $request->update([
                'status' => AccountSubscriptionRequestConstant::STATUS_APPROVED,
                'approved_by' => $adminUserId,
                'approved_at' => $now,
            ]);

            return $request->fresh(['account', 'subscriptionPlan']);
        });
    }

    public function reject(int $requestId, ?int $adminUserId = null, ?string $reason = null): AccountSubscriptionRequest
    {
        $request = $this->requestRepository->findPendingById($requestId);
        if (! $request) {
            throw new \InvalidArgumentException('Pending subscription request not found.');
        }

        $request->update([
            'status' => AccountSubscriptionRequestConstant::STATUS_REJECTED,
            'approved_by' => $adminUserId,
            'approved_at' => Carbon::now(),
            'rejection_reason' => $reason,
        ]);

        return $request->fresh(['account', 'subscriptionPlan']);
    }

    private function calculatePeriodEnd(Carbon $start, ?string $interval): Carbon
    {
        $copy = $start->copy();
        switch ($interval ?? '') {
            case AccountSubscriptionIntervalConstant::INTERVAL_QUARTER:
                return $copy->addMonths(3);
            case AccountSubscriptionIntervalConstant::INTERVAL_YEAR:
                return $copy->addYear();
            case AccountSubscriptionIntervalConstant::INTERVAL_MONTH:
            default:
                return $copy->addMonth();
        }
    }
}
