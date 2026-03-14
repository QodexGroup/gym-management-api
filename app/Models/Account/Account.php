<?php

namespace App\Models\Account;

use App\Constant\AccountStatusConstant;
use App\Models\Account\AccountSubscriptionPlan;
use App\Models\User;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends Model
{
    use HasCamelCaseAttributes;

    protected $table = 'accounts';

    protected $fillable = [
        'account_name',
        'account_email',
        'account_phone',
        'status',
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_address',
        'billing_city',
        'billing_province',
        'billing_zip',
        'billing_country',
    ];

    protected function casts(): array
    {
        return [];
    }

    /**
     * @return HasMany
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'account_id');
    }

    /**
     * @return HasMany
     */
    public function accountSubscriptionPlans(): HasMany
    {
        return $this->hasMany(AccountSubscriptionPlan::class);
    }

    /**
     * Current/latest active subscription plan record (trial or paid).
     *
     * @return HasOne
     */
    public function activeAccountSubscriptionPlan(): HasOne
    {
        return $this->hasOne(AccountSubscriptionPlan::class)->latestOfMany();
    }

    /**
     * @return bool
     */
    public function canCreatePaidResources(): bool
    {
        return $this->status === AccountStatusConstant::STATUS_ACTIVE;
    }

    /**
     * @return bool
     */
    public function isTrialExpired(): bool
    {
        $plan = $this->activeAccountSubscriptionPlan;
        if (!$plan || !$plan->trial_ends_at) {
            return false;
        }
        return $plan->trial_ends_at->isPast();
    }

    /**
     * @return SubscriptionPlan|null
     */
    public function getEffectivePlan(): ?SubscriptionPlan
    {
        $asp = $this->activeAccountSubscriptionPlan;
        if ($asp && $asp->subscriptionPlan) {
            return $asp->subscriptionPlan;
        }
        return SubscriptionPlan::where('slug', 'trial-subscription')->first();
    }
}
