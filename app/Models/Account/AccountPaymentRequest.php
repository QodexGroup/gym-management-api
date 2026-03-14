<?php

namespace App\Models\Account;

use App\Models\User;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccountPaymentRequest extends Model
{
    use HasFactory, HasCamelCaseAttributes;

    protected $table = 'account_payment_requests';

    protected $fillable = [
        'account_id',
        'payment_transaction',
        'payment_transaction_id',
        'amount',
        'receipt_url',
        'receipt_file_name',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'payment_details',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'amount' => 'decimal:2',
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
     * @return MorphTo
     */
    public function paymentTransaction(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'payment_transaction', 'payment_transaction_id');
    }

    /**
     * @return BelongsTo
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
