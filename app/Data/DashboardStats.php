<?php

namespace App\Data;

use Illuminate\Database\Eloquent\Collection;

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
    public Collection $membershipDistribution;
}
