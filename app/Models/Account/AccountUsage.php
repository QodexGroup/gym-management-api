<?php

namespace App\Models\Account;

use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A metered resource counter for an account (one row per account per resource).
 */
class AccountUsage extends Model
{
    use HasCamelCaseAttributes;

    protected $table = 'account_usages';

    protected $fillable = [
        'account_id',
        'resource_key',
        'used_amount',
        'limit_override',
    ];

    /**
     * Attribute casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used_amount' => 'decimal:2',
            'limit_override' => 'decimal:2',
        ];
    }

    /**
     * The account this usage counter belongs to.
     *
     * @return BelongsTo
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
