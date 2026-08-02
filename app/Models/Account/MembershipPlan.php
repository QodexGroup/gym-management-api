<?php

namespace App\Models\Account;

use App\Models\Core\CustomerMembership;
use App\Traits\HasCamelCaseAttributes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipPlan extends Model
{
    use HasFactory, HasCamelCaseAttributes, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tb_membership_plan';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_id',
        'plan_name',
        'price',
        'plan_period',
        'plan_interval',
        'features',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'plan_period' => 'integer',
            'features' => 'array',
        ];
    }

    /**
     * Read-only alias for `plan_name`.
     *
     * The column is `plan_name`, but `->name` is the instinctive spelling and reading it
     * used to yield null - which callers then swallowed with `?? 'N/A'`, silently shipping
     * "Your N/A membership is expiring soon" to customers. Aliasing it means both
     * spellings resolve instead of one failing quietly.
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => $this->plan_name);
    }

    /**
     * Get the customer memberships for this plan.
     */
    public function customerMemberships()
    {
        return $this->hasMany(CustomerMembership::class, 'membership_plan_id');
    }

    /**
     * Get the active customer memberships for this plan.
     */
    public function activeCustomerMemberships()
    {
        return $this->hasMany(CustomerMembership::class, 'membership_plan_id')
            ->where('status', 'active')
            ->whereDate('membership_end_date', '>=', today());
    }

    /**
     * Calculate membership end date based on start date and plan period/interval
     *
     * @param Carbon $startDate
     * @return Carbon
     */
    public function calculateEndDate(Carbon $startDate): Carbon
    {
        $endDate = match ($this->plan_interval) {
            'days' => $startDate->copy()->addDays($this->plan_period),
            'weeks' => $startDate->copy()->addWeeks($this->plan_period),
            'months' => $startDate->copy()->addMonths($this->plan_period),
            'years' => $startDate->copy()->addYears($this->plan_period),
            default => $startDate->copy()->addDays($this->plan_period),
        };

        // Store the last valid calendar day as the end date (inclusive),
        // aligned with the DATE cast on membership_end_date.
        return $endDate->subDay()->startOfDay();
    }
}
