<?php

namespace App\Models\Account;

use App\Constant\AccountInvoiceStatusConstant;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountInvoice extends Model
{
    use HasFactory, HasCamelCaseAttributes;

    protected $table = 'account_invoices';

    protected $fillable = [
        'account_id',
        'account_subscription_plan_id',
        'invoice_number',
        'billing_period',
        'invoice_date',
        'total_amount',
        'discount_amount',
        'status',
        'period_from',
        'period_to',
        'prorate',
        'invoice_details',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'total_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'period_from' => 'date',
            'period_to' => 'date',
            'prorate' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $invoice): void {
            if (empty($invoice->invoice_number)) {
                $invoice->updateQuietly([
                    'invoice_number' => self::formatInvoiceNumberFromId($invoice->id),
                ]);
            }
        });
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
    public function accountSubscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(AccountSubscriptionPlan::class, 'account_subscription_plan_id');
    }

    /**
     * @return HasMany
     */
    public function paymentRequests(): HasMany
    {
        return $this->hasMany(AccountPaymentRequest::class, 'payment_transaction_id')
            ->where('payment_transaction', self::class);
    }

    /**
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->status === AccountInvoiceStatusConstant::STATUS_PAID;
    }

    /**
     * @return bool
     */
    public function isUnpaid(): bool
    {
        return $this->status === AccountInvoiceStatusConstant::STATUS_PENDING;
    }

    /**
     * Format invoice number from ID.
     * @param int $id
     *
     * @return string
     */
    private static function formatInvoiceNumberFromId(int $id): string
    {
        return '#' . str_pad((string) $id, 7, '0', STR_PAD_LEFT);
    }
}
