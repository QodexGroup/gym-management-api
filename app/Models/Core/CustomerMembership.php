<?php

namespace App\Models\Core;

use App\Models\Account\MembershipPlan;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class CustomerMembership extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tb_customer_membership';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_id',
        'customer_id',
        'membership_plan_id',
        'membership_start_date',
        'membership_end_date',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'membership_start_date' => 'date',
            'membership_end_date' => 'date',
        ];
    }

    /**
     * Get the customer that owns this membership.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withTrashed();
    }

    /**
     * Get the membership plan for this membership.
     */
    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

     /**
     * Calculate membership end date based on period and interval
     *
     * @param Carbon $startDate
     * @param int $period
     * @param string $interval (days, weeks, months, years)
     * @return Carbon
     */
    public function calculateEndDate(Carbon $startDate, int $period, string $interval): Carbon
    {
        return match ($interval) {
            'days' => $startDate->copy()->addDays($period),
            'weeks' => $startDate->copy()->addWeeks($period),
            'months' => $startDate->copy()->addMonths($period),
            'years' => $startDate->copy()->addYears($period),
            default => $startDate->copy()->addDays($period),
        };
    }
}

