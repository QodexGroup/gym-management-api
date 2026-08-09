<?php

namespace App\Data;

class DashboardStats
{
    public int $totalMembers;
    public int $activeMembers;
    public int $newRegistrations;
    /** Payments actually collected today (cash basis). */
    public float $todayCollection;
    /** Amount billed today from non-voided bills (accrual basis), whether paid or not. */
    public float $todayRevenue;
    public int $expiringMemberships;
    public array $expiringMembersList;
    /** @var array<int, array{membershipPlanId: int, planName: string, count: int}> */
    public array $membershipDistribution;
}
