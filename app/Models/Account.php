<?php

namespace App\Models;

use App\Constant\AccountSubscriptionStatusConstant;
use App\Models\Account\AccountSubscriptionPlan;
use App\Models\Account\PlatformSubscriptionPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends Model
{
    protected $table = 'accounts';

    protected $fillable = [
        'name',
        'subscription_status',
        'owner_email',
        'legal_name',
        'billing_email',
        'address_line_1',
        'address_line_2',
        'city',
        'state_province',
        'postal_code',
        'country',
    ];

    protected function casts(): array
    {
        return [];
    }

    public const STATUS_TRIAL = AccountSubscriptionStatusConstant::STATUS_TRIAL;
    public const STATUS_ACTIVE = AccountSubscriptionStatusConstant::STATUS_ACTIVE;
    public const STATUS_TRIAL_EXPIRED = AccountSubscriptionStatusConstant::STATUS_TRIAL_EXPIRED;
    public const STATUS_PAST_DUE = AccountSubscriptionStatusConstant::STATUS_PAST_DUE;
    public const STATUS_CANCELLED = AccountSubscriptionStatusConstant::STATUS_CANCELLED;
    public const STATUS_LOCKED = AccountSubscriptionStatusConstant::STATUS_LOCKED;

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'account_id');
    }

    public function accountSubscriptionPlans(): HasMany
    {
        return $this->hasMany(AccountSubscriptionPlan::class);
    }

    /**
     * Current/latest active subscription plan record (trial or paid).
     */
    public function activeAccountSubscriptionPlan(): HasOne
    {
        return $this->hasOne(AccountSubscriptionPlan::class)->latestOfMany();
    }

    public function canCreatePaidResources(): bool
    {
        return in_array($this->subscription_status, [
            self::STATUS_TRIAL,
            self::STATUS_ACTIVE,
            self::STATUS_PAST_DUE,
        ], true);
    }

    public function isTrialExpired(): bool
    {
        if ($this->subscription_status !== self::STATUS_TRIAL) {
            return false;
        }
        $plan = $this->activeAccountSubscriptionPlan;
        if (!$plan || !$plan->trial_ends_at) {
            return false;
        }
        return $plan->trial_ends_at->isPast();
    }

    public function getEffectivePlan(): ?PlatformSubscriptionPlan
    {
        $asp = $this->activeAccountSubscriptionPlan;
        if ($asp && $asp->platformPlan) {
            return $asp->platformPlan;
        }
        return PlatformSubscriptionPlan::where('slug', 'trial')->first();
    }
}
