<?php

namespace App\Models\Account;

use App\Constant\ReferralConstant;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountReferral extends Model
{
    use HasCamelCaseAttributes;

    protected $table = 'account_referrals';

    protected $fillable = [
        'referrer_account_id',
        'invited_account_id',
        'referral_code',
        'status',
        'qualified_at',
        'reward_applied',
        'reward_applied_at',
        'reward_invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'qualified_at' => 'datetime',
            'reward_applied' => 'boolean',
            'reward_applied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo
     */
    public function referrerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'referrer_account_id');
    }

    /**
     * @return BelongsTo
     */
    public function invitedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'invited_account_id');
    }

    /**
     * @return BelongsTo
     */
    public function rewardInvoice(): BelongsTo
    {
        return $this->belongsTo(AccountInvoice::class, 'reward_invoice_id');
    }

    /**
     * Qualified referrals whose reward has not yet been consumed by a discount.
     * The presence of at least one such row makes the referrer eligible for a 5% discount.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeQualifiedUnapplied(Builder $query): Builder
    {
        return $query->where('status', ReferralConstant::STATUS_QUALIFIED)
            ->where('reward_applied', false);
    }
}
