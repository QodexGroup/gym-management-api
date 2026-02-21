<?php

namespace App\Models\Common;

use App\Models\Core\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasCamelCaseAttributes;

class WalkinCustomer extends Model
{
    use HasFactory, SoftDeletes, HasCamelCaseAttributes;

    protected $table = 'tb_customer_walkin';
    protected $guarded = ['id'];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
    ];

    /**
     * @return BelongsTo
     */
    public function walkin(): BelongsTo
    {
        return $this->belongsTo(Walkin::class, 'walkin_id', 'id');
    }

    /**
     * @return BelongsTo
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

}
