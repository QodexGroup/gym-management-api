<?php

namespace App\Models;

use App\Models\Account\PlatformSubscriptionPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $table = 'accounts';

    protected $fillable = [
        'name',
        'subscription_status',
        'subscription_plan_id',
        'trial_ends_at',
        'current_period_ends_at',
        'stripe_customer_id',
        'stripe_subscription_id',
        'owner_email',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
        ];
    }

    public const STATUS_TRIAL = 'trial';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_TRIAL_EXPIRED = 'trial_expired';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELLED = 'cancelled';

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(PlatformSubscriptionPlan::class, 'subscription_plan_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'account_id');
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
