<?php

namespace App\Models\Account;

use App\Constant\SubscriptionPlanResourceConstant;
use App\Models\Account;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory, HasCamelCaseAttributes;

    protected $table = 'subscription_plans';

    protected $fillable = [
        'name',
        'slug',
        'interval',
        'price',
        'max_customers',
        'max_class_schedules',
        'max_membership_plans',
        'max_users',
        'max_pt_packages',
        'has_pt',
        'has_reports',
        'trial_days',
        'is_trial',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'has_pt' => 'boolean',
            'has_reports' => 'boolean',
            'is_trial' => 'boolean',
        ];
    }

    /**
     * @return HasMany
     */
    public function accountSubscriptionPlans(): HasMany
    {
        return $this->hasMany(AccountSubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * @param string $resource
     * @return int
     */
    public function getLimit(string $resource): int
    {
        $column = SubscriptionPlanResourceConstant::getColumnForResource($resource);
        return $column ? (int) $this->{$column} : 0;
    }

    /**
     * @param string $resource
     * @return bool
     */
    public function isUnlimited(string $resource): bool
    {
        return $this->getLimit($resource) === 0;
    }
}
