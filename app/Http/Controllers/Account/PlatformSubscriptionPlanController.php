<?php

namespace App\Http\Controllers\Account;

use App\Helpers\ApiResponse;
use App\Models\Account\PlatformSubscriptionPlan;
use Illuminate\Http\JsonResponse;

class PlatformSubscriptionPlanController
{
    /**
     * List platform subscription plans (for subscription/upgrade page).
     */
    public function index(): JsonResponse
    {
        $plans = PlatformSubscriptionPlan::where('is_trial', false)
            ->orderBy('price')
            ->get();

        return ApiResponse::success($plans->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'interval' => $p->interval,
                'price' => (float) $p->price,
                'maxCustomers' => $p->max_customers,
                'maxClassSchedules' => $p->max_class_schedules,
                'maxMembershipPlans' => $p->max_membership_plans,
                'maxUsers' => $p->max_users,
                'maxPtPackages' => $p->max_pt_packages,
                'hasPt' => $p->has_pt,
                'hasReports' => $p->has_reports,
                'isTrial' => (bool) $p->is_trial,
            ];
        }));
    }
}
