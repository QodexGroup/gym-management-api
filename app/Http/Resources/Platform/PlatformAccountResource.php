<?php

namespace App\Http\Resources\Platform;

use App\Support\Platform\AccountStatusResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One account as the console's list renders it.
 *
 * `derived_status`, `owner_name`, `owner_email`, `outstanding_amount` and
 * `pending_invoice_count` arrive as query-level extras from
 * PlatformAccountRepository. The fallbacks exist so this resource still works
 * on a plain model, but the repository is the intended path.
 *
 * @mixin \App\Models\Account\Account
 */
class PlatformAccountResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $plan = $this->activeAccountSubscriptionPlan;

        return [
            'id' => $this->id,
            'name' => $this->account_name,
            'ownerName' => $this->owner_name,
            'ownerEmail' => $this->owner_email ?? $this->account_email,
            'status' => $this->derived_status ?? AccountStatusResolver::resolve($this->resource),
            'plan' => $plan?->plan_name ?? $plan?->subscriptionPlan?->name,
            'coverageEndsAt' => $plan?->subscription_ends_at?->toIso8601String(),
            'trialEndsAt' => $plan?->trial_ends_at?->toIso8601String(),
            'lockedAt' => $plan?->locked_at?->toIso8601String(),
            'pendingInvoices' => (int) ($this->pending_invoice_count ?? 0),
            'outstanding' => round((float) ($this->outstanding_amount ?? 0), 2),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
