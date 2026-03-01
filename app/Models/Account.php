<?php

namespace App\Models;

use App\Constant\AccountSubscriptionStatusConstant;
use App\Models\Account\AccountBillingInformation;
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
        'subscription_plan_id',
        'trial_ends_at',
        'current_period_ends_at',
        'owner_email',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
        ];
    }

    public const STATUS_TRIAL = AccountSubscriptionStatusConstant::STATUS_TRIAL;
    public const STATUS_ACTIVE = AccountSubscriptionStatusConstant::STATUS_ACTIVE;
    public const STATUS_TRIAL_EXPIRED = AccountSubscriptionStatusConstant::STATUS_TRIAL_EXPIRED;
    public const STATUS_PAST_DUE = AccountSubscriptionStatusConstant::STATUS_PAST_DUE;
    public const STATUS_CANCELLED = AccountSubscriptionStatusConstant::STATUS_CANCELLED;

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(PlatformSubscriptionPlan::class, 'subscription_plan_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'account_id');
    }

    public function billingInformation(): HasOne
    {
        return $this->hasOne(AccountBillingInformation::class);
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
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    public function getEffectivePlan(): ?PlatformSubscriptionPlan
    {
        if ($this->subscription_plan_id) {
            return $this->subscriptionPlan;
        }
        return PlatformSubscriptionPlan::where('slug', 'trial')->first();
    }
}
