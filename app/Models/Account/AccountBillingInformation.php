<?php

namespace App\Models\Account;

use App\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBillingInformation extends Model
{
    protected $table = 'account_billing_information';

    protected $fillable = [
        'account_id',
        'legal_name',
        'business_name',
        'billing_email',
        'tax_id',
        'vat_number',
        'address_line_1',
        'address_line_2',
        'city',
        'state_province',
        'postal_code',
        'country',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
