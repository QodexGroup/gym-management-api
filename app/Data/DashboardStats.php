<?php

namespace App\Data;

use Illuminate\Database\Eloquent\Collection;

class DashboardStats
{
    public int $totalMembers;
    public int $activeMembers;
    public int $newRegistrations;
    public float $todayRevenue;
    public int $expiringMemberships;
    public array $expiringMembersList;
    public Collection $membershipDistribution;
}
