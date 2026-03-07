<?php

namespace App\Models\Account;

use App\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountInvoice extends Model
{
    protected $table = 'account_invoices';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'account_id',
        'account_subscription_plan_id',
        'invoice_number',
        'billing_period',
        'plan_name',
        'plan_interval',
        'plan_price',
        'billing_cycle_start_at',
        'status',
        'invoice_details',
    ];

    protected function casts(): array
    {
        return [
            'plan_price' => 'decimal:2',
            'billing_cycle_start_at' => 'date',
            'invoice_details' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function accountSubscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(AccountSubscriptionPlan::class, 'account_subscription_plan_id');
    }

    public function subscriptionRequests(): HasMany
    {
        return $this->hasMany(AccountSubscriptionRequest::class, 'account_invoice_id');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isUnpaid(): bool
    {
        return in_array($this->status, [self::STATUS_ISSUED, self::STATUS_OVERDUE], true);
    }
}
