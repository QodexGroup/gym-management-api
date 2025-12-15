<?php

namespace App\Models\Core;

use App\Models\User;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, HasCamelCaseAttributes, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tb_customers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_id',
        'balance',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'photo',
        'phone_number',
        'email',
        'address',
        'medical_notes',
        'emergency_contact_name',
        'emergency_contact_phone',
        'blood_type',
        'allergies',
        'current_medications',
        'medical_conditions',
        'doctor_name',
        'doctor_phone',
        'insurance_provider',
        'insurance_policy_number',
        'emergency_contact_relationship',
        'emergency_contact_address',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'balance' => 'decimal:2',
        ];
    }

    /**
     * Get the memberships for the customer.
     */
    public function memberships()
    {
        return $this->hasMany(CustomerMembership::class, 'customer_id');
    }

    /**
     * Get the active membership for the customer.
     */
    public function activeMembership()
    {
        return $this->hasOne(CustomerMembership::class, 'customer_id')
            ->where('status', 'active')
            ->where('membership_end_date', '>=', now())
            ->latest('membership_start_date');
    }

    /**
     * Get the current membership for the customer (most recent).
     */
    public function currentMembership()
    {
        return $this->hasOne(CustomerMembership::class, 'customer_id')
            ->orderBy('membership_start_date', 'desc')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get the trainers assigned to this customer.
     */
    public function trainers()
    {
        return $this->belongsToMany(User::class, 'tb_customer_trainor', 'customer_id', 'trainer_id')
            ->withTimestamps();
    }

    /**
     * Get the current trainer for the customer (most recently assigned).
     */
    public function currentTrainer()
    {
        return $this->belongsToMany(User::class, 'tb_customer_trainor', 'customer_id', 'trainer_id')
            ->withTimestamps()
            ->orderByPivot('created_at', 'desc')
            ->limit(1);
    }

    /**
     * Get the progress records for the customer.
     */
    public function progressRecords()
    {
        return $this->hasMany(CustomerProgress::class, 'customer_id');
    }

    /**
     * Get the scans for the customer.
     */
    public function scans()
    {
        return $this->hasMany(CustomerScans::class, 'customer_id');
    }

    /**
     * Get the files for the customer.
     */
    public function files()
    {
        return $this->hasMany(CustomerFiles::class, 'customer_id');
    }

    /**
     * Get the bills for the customer.
     */
    public function bills()
    {
        return $this->hasMany(CustomerBill::class, 'customer_id');
    }

    /**
     * Calculate customer balance from bills
     * Balance = Total Net Amount - Total Paid Amount
     *
     * @return float
     */
    public function calculateBalance(): float
    {
        $totalNetAmount = $this->bills()
            ->where('account_id', $this->account_id)
            ->sum('net_amount') ?? 0;

        $totalPaidAmount = $this->bills()
            ->where('account_id', $this->account_id)
            ->sum('paid_amount') ?? 0;

        return (float) ($totalNetAmount - $totalPaidAmount);
    }

    /**
     * Recalculate and update customer balance based on bills
     *
     * @return bool
     */
    public function recalculateBalance(): bool
    {
        $newBalance = $this->calculateBalance();
        $this->balance = $newBalance;
        return $this->save();
    }
}

