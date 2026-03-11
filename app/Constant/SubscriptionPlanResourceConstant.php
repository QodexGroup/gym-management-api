<?php

namespace App\Constant;

/**
 * Subscription plan resource types and their corresponding column names.
 */
class SubscriptionPlanResourceConstant
{
    const RESOURCE_CUSTOMERS = 'customers';
    const RESOURCE_CLASS_SCHEDULES = 'class_schedules';
    const RESOURCE_MEMBERSHIP_PLANS = 'membership_plans';
    const RESOURCE_USERS = 'users';
    const RESOURCE_PT_PACKAGES = 'pt_packages';

    /**
     * Map of resource names to their corresponding column names in subscription_plans table.
     *
     * @var array<string, string>
     */
    const RESOURCE_TO_COLUMN_MAP = [
        self::RESOURCE_CUSTOMERS => 'max_customers',
        self::RESOURCE_CLASS_SCHEDULES => 'max_class_schedules',
        self::RESOURCE_MEMBERSHIP_PLANS => 'max_membership_plans',
        self::RESOURCE_USERS => 'max_users',
        self::RESOURCE_PT_PACKAGES => 'max_pt_packages',
    ];

    /**
     * Get the column name for a given resource.
     *
     * @param string $resource
     * @return string|null
     */
    public static function getColumnForResource(string $resource): ?string
    {
        return self::RESOURCE_TO_COLUMN_MAP[$resource] ?? null;
    }
}
