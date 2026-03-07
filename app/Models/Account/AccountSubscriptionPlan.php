<?php

namespace App\Models\Account;

use App\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountSubscriptionPlan extends Model
{
    protected $table = 'account_subscription_plans';

    protected $fillable = [
        'account_id',
        'platform_subscription_plan_id',
        'trial_starts_at',
        'trial_ends_at',
        'subscription_starts_at',
        'subscription_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'subscription_starts_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function platformPlan(): BelongsTo
    {
        return $this->belongsTo(PlatformSubscriptionPlan::class, 'platform_subscription_plan_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(AccountInvoice::class, 'account_subscription_plan_id');
    }
}
