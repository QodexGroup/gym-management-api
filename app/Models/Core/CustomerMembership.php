<?php

namespace App\Models\Core;

use App\Constant\CustomerMembershipConstant;
use App\Models\Account\MembershipPlan;
use App\Observers\CustomerMembershipObserver;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[ObservedBy(CustomerMembershipObserver::class)]
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
        'pending_plan_id',
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
     * Constrain the query to memberships in the given derived status.
     *
     * This is the ONE definition of what active / expiring / expired mean, and
     * the three are mutually exclusive - every non-deleted membership falls in
     * exactly one of them:
     *
     *   expired  - explicitly expired, or simply past its end date
     *   expiring - active, ending today through today + EXPIRING_SOON_DAYS
     *   active   - active, ending after that window
     *
     * Mirrors getCustomerMembershipDisplayStatus() on the frontend, which picks
     * one badge per row the same way, so a stat card and the badges below it
     * always tell the same story.
     *
     * @param Builder $query
     * @param string $status One of CustomerMembershipConstant::FILTERABLE_STATUSES
     * @return Builder
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        $today = today()->toDateString();
        $expiringThrough = today()->addDays(CustomerMembershipConstant::EXPIRING_SOON_DAYS)->toDateString();

        return match ($status) {
            CustomerMembershipConstant::STATUS_EXPIRING => $query
                ->where('status', CustomerMembershipConstant::STATUS_ACTIVE)
                ->whereBetween('membership_end_date', [$today, $expiringThrough]),

            CustomerMembershipConstant::STATUS_EXPIRED => $query
                ->where(fn (Builder $q) => $q
                    ->where('status', CustomerMembershipConstant::STATUS_EXPIRED)
                    ->orWhere('membership_end_date', '<', $today)),

            // Deliberately excludes the expiring window: an expiring membership
            // is counted and filtered as expiring only, never as active too.
            default => $query
                ->where('status', CustomerMembershipConstant::STATUS_ACTIVE)
                ->where('membership_end_date', '>', $expiringThrough),
        };
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
     * Get the plan scheduled to take effect at the next renewal (if any).
     */
    public function pendingPlan()
    {
        return $this->belongsTo(MembershipPlan::class, 'pending_plan_id');
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

