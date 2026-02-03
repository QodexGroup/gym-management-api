<?php

namespace App\Models\Core;

use App\Models\Account\PtPackage;
use App\Models\User;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPtPackage extends Model
{
    use HasFactory, HasCamelCaseAttributes, SoftDeletes;

    protected $table = 'tb_customer_pt_package';

    protected $fillable = [
        'account_id',
        'customer_id',
        'pt_package_id',
        'coach_id',
        'start_date',
        'status',
        'number_of_sessions_remaining',
        'created_by',
        'updated_by',
    ];

    /**
     * @return BelongsTo
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return BelongsTo
     */
    public function ptPackage(): BelongsTo
    {
        return $this->belongsTo(PtPackage::class, 'pt_package_id');
    }
    /**
     * @return BelongsTo
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }
}
