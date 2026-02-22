<?php

namespace App\Models\Account;

use App\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformSubscriptionPlan extends Model
{
    protected $table = 'platform_subscription_plans';

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

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'subscription_plan_id');
    }

    public function getLimit(string $resource): int
    {
        $column = null;
        switch ($resource) {
            case 'customers':
                $column = 'max_customers';
                break;
            case 'class_schedules':
                $column = 'max_class_schedules';
                break;
            case 'membership_plans':
                $column = 'max_membership_plans';
                break;
            case 'users':
                $column = 'max_users';
                break;
            case 'pt_packages':
                $column = 'max_pt_packages';
                break;
        }

        return $column ? (int) $this->{$column} : 0;
    }

    public function isUnlimited(string $resource): bool
    {
        return $this->getLimit($resource) === 0;
    }
}
