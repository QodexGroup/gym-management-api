<?php

namespace App\Models\Account;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountSubscriptionRequest extends Model
{
    protected $table = 'account_subscription_requests';

    protected $fillable = [
        'account_id',
        'subscription_plan_id',
        'receipt_url',
        'receipt_file_name',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(PlatformSubscriptionPlan::class, 'subscription_plan_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
