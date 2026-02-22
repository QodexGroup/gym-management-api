<?php

namespace App\Services\Account;

use App\Helpers\GenericData;
use App\Models\Account;
use App\Models\Account\AccountSubscriptionRequest;
use App\Models\Account\PlatformSubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccountSubscriptionRequestService
{
    public function createRequest(GenericData $genericData): AccountSubscriptionRequest
    {
        $data = $genericData->getData();
        $accountId = $genericData->userData->account_id;

        $plan = PlatformSubscriptionPlan::where('id', $data->subscriptionPlanId)
            ->where('is_trial', false)
            ->firstOrFail();

        $account = Account::findOrFail($accountId);

        $currentPeriodEnds = $account->current_period_ends_at;
        if ($account->subscription_status === Account::STATUS_ACTIVE && $currentPeriodEnds && $currentPeriodEnds->isFuture()) {
            throw new \Exception('Account already has an active subscription. Upgrade or change plan from settings.');
        }

        $existingPending = AccountSubscriptionRequest::where('account_id', $accountId)
            ->where('status', AccountSubscriptionRequest::STATUS_PENDING)
            ->exists();

        if ($existingPending) {
            throw new \Exception('You already have a pending subscription request. Please wait for approval.');
        }

        return AccountSubscriptionRequest::create([
            'account_id' => $accountId,
            'subscription_plan_id' => $plan->id,
            'receipt_url' => $data->receiptUrl,
            'receipt_file_name' => $data->receiptFileName ?? null,
            'status' => AccountSubscriptionRequest::STATUS_PENDING,
            'requested_by' => $genericData->userData->id,
        ]);
    }

    public function approve(int $requestId, ?int $adminUserId = null): AccountSubscriptionRequest
    {
        return DB::transaction(function () use ($requestId, $adminUserId) {
            $request = AccountSubscriptionRequest::with(['account', 'subscriptionPlan'])
                ->where('status', AccountSubscriptionRequest::STATUS_PENDING)
                ->findOrFail($requestId);

            $plan = $request->subscriptionPlan;
            $account = $request->account;

            $now = Carbon::now();
            $periodEndsAt = $this->calculatePeriodEnd($now, $plan->interval);

            $account->update([
                'subscription_status' => Account::STATUS_ACTIVE,
                'subscription_plan_id' => $plan->id,
                'current_period_ends_at' => $periodEndsAt,
                'trial_ends_at' => null,
            ]);

            $request->update([
                'status' => AccountSubscriptionRequest::STATUS_APPROVED,
                'approved_by' => $adminUserId,
                'approved_at' => $now,
            ]);

            return $request->fresh(['account', 'subscriptionPlan']);
        });
    }

    public function reject(int $requestId, ?int $adminUserId = null, ?string $reason = null): AccountSubscriptionRequest
    {
        $request = AccountSubscriptionRequest::where('status', AccountSubscriptionRequest::STATUS_PENDING)
            ->findOrFail($requestId);

        $request->update([
            'status' => AccountSubscriptionRequest::STATUS_REJECTED,
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
            case 'quarter':
                return $copy->addMonths(3);
            case 'year':
                return $copy->addYear();
            case 'month':
            default:
                return $copy->addMonth();
        }
    }
}
