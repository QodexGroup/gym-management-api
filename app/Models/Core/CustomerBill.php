<?php

namespace App\Models\Core;

use App\Models\User;
use App\Models\Account\MembershipPlan;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerBill extends Model
{
    use HasFactory, HasCamelCaseAttributes, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tb_customer_bills';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_id',
        'customer_id',
        'gross_amount',
        'discount_percentage',
        'net_amount',
        'paid_amount',
        'bill_date',
        'billing_period',
        'bill_status',
        'bill_type',
        'billable_id',
        'custom_service',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'gross_amount' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the customer that owns the bill.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the user who created the bill.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who updated the bill.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the membership plan for the bill (if bill type is membership subscription).
     * Plan id is stored in billable_id for subscription bills (polymorphic bills table).
     */
    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'billable_id');
    }

    /**
     * Get the customer PT package for the bill (if bill type is PT package subscription).
     * Matches on customer_id and billable_id = pt_package_id
     */
    public function customerPtPackage(): HasOne
    {
        return $this->hasOne(CustomerPtPackage::class, 'customer_id', 'customer_id')
            ->whereColumn('tb_customer_pt_package.pt_package_id', $this->getTable() . '.billable_id');
    }
}
