<?php

namespace App\Models\Account;

use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountSubscriptionPlan extends Model
{
    use HasFactory, HasCamelCaseAttributes;

    protected $table = 'account_subscription_plans';

    protected $fillable = [
        'account_id',
        'subscription_plan_id',
        'plan_name',
        'trial_starts_at',
        'trial_ends_at',
        'subscription_starts_at',
        'subscription_ends_at',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'subscription_starts_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * @return HasMany
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(AccountInvoice::class, 'account_subscription_plan_id');
    }
}
