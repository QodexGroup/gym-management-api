<?php

namespace App\Services\Core;

use App\Constant\ClassSessionBookingStatusConstant;
use App\Constant\ClassTypeScheduleConstant;
use App\Constant\CustomerPtPackageConstant;
use App\Models\Account\ClassScheduleSession;
use App\Models\Core\ClassSessionBooking;
use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use App\Models\Core\CustomerPayment;
use App\Models\Core\CustomerPtPackage;
use App\Models\Core\PtBooking;
use App\Models\Account\MembershipPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get dashboard statistics
     *
     * @return array
     */
    public function getStats(int $accountId): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        // 7 days aligns with CheckMembershipExpiration / notify-member window
        $sevenDaysFromNow = $now->copy()->addDays(7);

        return [
            'totalMembers' => $this->getTotalMembers($accountId),
            'activeMembers' => $this->getActiveMembers($accountId),
            'newRegistrations' => $this->getNewRegistrations($accountId, $startOfMonth, $endOfMonth),
            'todayRevenue' => $this->getTodayRevenue($accountId, $now),
            'expiringMemberships' => $this->getExpiringMembershipsCount($accountId, $now, $sevenDaysFromNow),
            'expiringMembersList' => $this->getExpiringMembersList($accountId, $now, $sevenDaysFromNow),
            'membershipDistribution' => $this->getMembershipDistribution($accountId),
        ];
    }

    /**
     * Minimal dashboard payload for coaches (no revenue or account-wide metrics).
     *
     * @return array{expiringMembersList: array}
     */
    public function getCoachDashboardStats(int $accountId): array
    {
        $now = Carbon::now();
        $sevenDaysFromNow = $now->copy()->addDays(7);

        return [
            'expiringMembersList' => $this->getExpiringMembersList($accountId, $now, $sevenDaysFromNow),
        ];
    }

    /**
     * Get total number of members
     *
     * @return int
     */
    private function getTotalMembers(int $accountId): int
    {
        return Customer::where('account_id', $accountId)->count();
    }

    /**
     * Get number of active members
     *
     * @return int
     */
    private function getActiveMembers(int $accountId): int
    {
        return Customer::where('account_id', $accountId)->whereHas('memberships', function ($query) use ($accountId) {
            $query->where('status', 'Active')
                  ->where('account_id', $accountId)
                  ->whereDate('membership_end_date', '>=', today());
        })->count();
    }

    /**
     * Get new registrations for the current month
     *
     * @param Carbon $startOfMonth
     * @param Carbon $endOfMonth
     * @return int
     */
    private function getNewRegistrations(int $accountId, Carbon $startOfMonth, Carbon $endOfMonth): int
    {
        return Customer::where('account_id', $accountId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();
    }

    /**
     * Get today's revenue
     *
     * @param Carbon $now
     * @return float
     */
    private function getTodayRevenue(int $accountId, Carbon $now): float
    {
        $startOfDay = $now->copy()->startOfDay();
        $endOfDay = $now->copy()->endOfDay();

        return CustomerPayment::where('account_id', $accountId)
            ->whereBetween('payment_date', [$startOfDay, $endOfDay])
            ->sum('amount') ?? 0.0;
    }

    /**
     * Get count of memberships expiring in the next 7 days (aligns with notify-member window).
     *
     * @param Carbon $now
     * @param Carbon $sevenDaysFromNow
     * @return int
     */
    private function getExpiringMembershipsCount(int $accountId, Carbon $now, Carbon $sevenDaysFromNow): int
    {
        return CustomerMembership::where('account_id', $accountId)
            ->where('status', 'Active')
            ->whereBetween('membership_end_date', [$now->copy()->startOfDay(), $sevenDaysFromNow->copy()->endOfDay()])
            ->count();
    }

    /**
     * Get list of members with expiring memberships (next 7 days, aligns with notify-member window).
     *
     * @param Carbon $now
     * @param Carbon $sevenDaysFromNow
     * @return array
     */
    private function getExpiringMembersList(int $accountId, Carbon $now, Carbon $sevenDaysFromNow): array
    {
        $expiringMemberships = CustomerMembership::with(['customer', 'membershipPlan'])
            ->where('account_id', $accountId)
            ->where('status', 'Active')
            ->whereBetween('membership_end_date', [$now->copy()->startOfDay(), $sevenDaysFromNow->copy()->endOfDay()])
            ->orderBy('membership_end_date', 'asc')
            ->limit(10)
            ->get();

        return $expiringMemberships->map(function ($membership) use ($now) {
            $daysUntilExpiry = $now->diffInDays($membership->membership_end_date, false);

            $customerName = trim(($membership->customer->first_name ?? '') . ' ' . ($membership->customer->last_name ?? ''));
            if (empty($customerName)) {
                $customerName = 'Unknown';
            }

            // Determine status based on days until expiry (same as CustomerRepository: valid until end of day)
            if ($membership->membership_end_date->toDateString() < today()->toDateString()) {
                $status = 'expired';
            } elseif ($daysUntilExpiry <= 7) {
                $status = 'expiring';
            } else {
                $status = 'active';
            }

            return [
                'id' => $membership->customer->id,
                'name' => $customerName,
                'email' => $membership->customer->email,
                'membership' => $membership->membershipPlan->plan_name ?? 'N/A',
                'membershipExpiry' => $membership->membership_end_date->format('Y-m-d'),
                'membershipStatus' => $status,
                'avatar' => $membership->customer->photo ?? null,
            ];
        })->toArray();
    }

    /**
     * Get membership distribution by plan
     *
     * @return array
     */
    private function getMembershipDistribution(int $accountId): array
    {
        // Get the latest membership ID for each customer
        $latestMembershipIds = CustomerMembership::select('id')
            ->where('account_id', $accountId)
            ->whereIn('id', function($query) use ($accountId) {
                $query->selectRaw('MAX(id)')
                    ->from('tb_customer_membership')
                    ->where('account_id', $accountId)
                    ->where('status', 'Active')
                    ->groupBy('customer_id');
            })
            ->pluck('id');

        // Get distribution based on latest memberships only
        $distribution = CustomerMembership::select('membership_plan_id', DB::raw('count(*) as count'))
            ->where('account_id', $accountId)
            ->whereIn('id', $latestMembershipIds)
            ->groupBy('membership_plan_id')
            ->with('membershipPlan')
            ->get();

        $colors = ['#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];

        return $distribution->map(function ($item, $index) use ($colors) {
            return [
                'name' => $item->membershipPlan->plan_name ?? 'Unknown',
                'value' => $item->count,
                'color' => $colors[$index % count($colors)],
            ];
        })->toArray();
    }

    /**
     * Upcoming class schedule sessions (from start of today) with participant names for dashboards.
     *
     * @return array{sessions: array<int, array<string, mixed>>}
     */
    public function getUpcomingSessionsWithParticipants(int $accountId, ?int $coachUserId, bool $scopeSessionsToCoach, int $limit = 10): array
    {
        $startOfToday = Carbon::now()->startOfDay();

        $query = ClassScheduleSession::query()
            ->where('account_id', $accountId)
            ->where('start_time', '>=', $startOfToday)
            ->with(['classSchedule.coach']);

        if ($scopeSessionsToCoach && $coachUserId !== null) {
            $query->whereHas('classSchedule', function ($q) use ($coachUserId) {
                $q->where('coach_id', $coachUserId);
            });
        }

        $sessions = $query->orderBy('start_time')->limit($limit)->get();

        if ($sessions->isEmpty()) {
            return ['sessions' => []];
        }

        $groupSessionIds = $sessions->filter(function ($s) {
            return $s->classSchedule
                && $s->classSchedule->class_type === ClassTypeScheduleConstant::GROUP_CLASS;
        })->pluck('id');

        $groupBookingsBySession = collect();
        if ($groupSessionIds->isNotEmpty()) {
            $groupBookingsBySession = ClassSessionBooking::where('account_id', $accountId)
                ->whereIn('class_schedule_session_id', $groupSessionIds)
                ->whereNotIn('status', [
                    ClassSessionBookingStatusConstant::STATUS_CANCELLED,
                    ClassSessionBookingStatusConstant::STATUS_COACH_CANCELLED,
                ])
                ->with('customer')
                ->get()
                ->groupBy(fn ($b) => (int) $b->class_schedule_session_id);
        }

        $ptSessions = $sessions->filter(function ($s) {
            return $s->classSchedule
                && $s->classSchedule->class_type === ClassTypeScheduleConstant::PERSONAL_TRAINING;
        });

        $scheduleIds = $ptSessions->pluck('class_schedule_id')->unique()->filter()->values();
        $ptBookingsBySessionKey = collect();
        $ptBookingsByScheduleDate = collect();
        if ($scheduleIds->isNotEmpty()) {
            $ptDateMin = $ptSessions->min('start_time');
            $ptDateMax = $ptSessions->max('start_time');
            $bookingFrom = $ptDateMin
                ? Carbon::parse($ptDateMin)->copy()->subDay()->startOfDay()
                : $startOfToday->copy()->subDay();
            $bookingTo = $ptDateMax
                ? Carbon::parse($ptDateMax)->copy()->addDay()->endOfDay()
                : $startOfToday->copy()->addDays(31)->endOfDay();

            $ptBookings = PtBooking::where('account_id', $accountId)
                ->whereIn('class_schedule_id', $scheduleIds)
                ->whereBetween('booking_date', [$bookingFrom->toDateString(), $bookingTo->toDateString()])
                ->whereNotIn('status', [
                    ClassSessionBookingStatusConstant::STATUS_CANCELLED,
                    ClassSessionBookingStatusConstant::STATUS_COACH_CANCELLED,
                ])
                ->with('customer')
                ->get();

            $ptBookingsBySessionKey = $ptBookings->groupBy(function ($pb) {
                return (int) $pb->class_schedule_id.'|'
                    .Carbon::parse($pb->booking_date)->format('Y-m-d').'|'
                    .(int) $pb->coach_id;
            });

            $ptBookingsByScheduleDate = $ptBookings->groupBy(function ($pb) {
                return (int) $pb->class_schedule_id.'|'
                    .Carbon::parse($pb->booking_date)->format('Y-m-d');
            });
        }

        $out = [];
        foreach ($sessions as $session) {
            $schedule = $session->classSchedule;
            if (!$schedule) {
                continue;
            }

            $participants = [];
            if ($schedule->class_type === ClassTypeScheduleConstant::GROUP_CLASS) {
                $bookings = $groupBookingsBySession->get((int) $session->id, collect());
                foreach ($bookings as $b) {
                    if ($b->customer) {
                        $participants[] = [
                            'customerId' => $b->customer->id,
                            'name' => $this->formatCustomerFullName($b->customer),
                        ];
                    }
                }
            } else {
                $sessionDate = $session->start_time->format('Y-m-d');
                $ptKey = (int) $schedule->id.'|'.$sessionDate.'|'.(int) $schedule->coach_id;
                $matched = $ptBookingsBySessionKey->get($ptKey, collect());
                if ($matched->isEmpty()) {
                    $matched = $ptBookingsByScheduleDate->get((int) $schedule->id.'|'.$sessionDate, collect());
                }
                foreach ($matched as $pb) {
                    if ($pb->customer) {
                        $participants[] = [
                            'customerId' => $pb->customer->id,
                            'name' => $this->formatCustomerFullName($pb->customer),
                        ];
                    }
                }
            }

            $coach = $schedule->coach;
            $out[] = [
                'id' => $session->id,
                'startTime' => $session->start_time->toIso8601String(),
                'endTime' => $session->end_time ? $session->end_time->toIso8601String() : null,
                'className' => $schedule->class_name,
                'classType' => $schedule->class_type,
                'coach' => $coach ? [
                    'id' => $coach->id,
                    'firstname' => $coach->firstname,
                    'lastname' => $coach->lastname,
                    'fullName' => $coach->full_name,
                ] : null,
                'participants' => $participants,
            ];
        }

        return ['sessions' => $out];
    }

    /**
     * Customers with at least one active PT package assigned to the coach.
     *
     * @return array{members: array<int, array<string, mixed>>, total: int}
     */
    public function getCoachAssignedPtClients(int $accountId, int $coachId, int $limit = 10): array
    {
        $ptTable = (new CustomerPtPackage())->getTable();

        $base = Customer::query()
            ->where('account_id', $accountId)
            ->whereIn('id', function ($sub) use ($accountId, $coachId, $ptTable) {
                $sub->select('customer_id')
                    ->from($ptTable)
                    ->where('account_id', $accountId)
                    ->where('coach_id', $coachId)
                    ->where('status', CustomerPtPackageConstant::STATUS_ACTIVE)
                    ->whereNull('deleted_at');
            });

        $total = (clone $base)->count();

        $customers = (clone $base)
            ->with(['currentMembership.membershipPlan'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit($limit)
            ->get();

        $now = Carbon::now();
        $sevenDaysFromNow = $now->copy()->addDays(7);

        $members = $customers->map(function (Customer $c) use ($now, $sevenDaysFromNow) {
            $m = $c->currentMembership;
            $planName = $m && $m->membershipPlan ? $m->membershipPlan->plan_name : '—';
            $membershipStatus = $this->deriveMembershipDisplayStatus($m, $now, $sevenDaysFromNow);

            return [
                'id' => $c->id,
                'name' => $this->formatCustomerFullName($c),
                'firstName' => $c->first_name,
                'lastName' => $c->last_name,
                'email' => $c->email,
                'photo' => $c->photo,
                'membership' => $planName,
                'membershipStatus' => $membershipStatus,
            ];
        })->toArray();

        return [
            'members' => $members,
            'total' => $total,
        ];
    }

    private function formatCustomerFullName(Customer $customer): string
    {
        $name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));

        return $name !== '' ? $name : 'Unknown';
    }

    private function deriveMembershipDisplayStatus(?CustomerMembership $membership, Carbon $now, Carbon $sevenDaysFromNow): string
    {
        if (!$membership || strtolower((string) $membership->status) !== 'active') {
            return 'expired';
        }

        $end = $membership->membership_end_date
            ? Carbon::parse($membership->membership_end_date)->copy()->startOfDay()
            : null;

        if (!$end) {
            return 'active';
        }

        if ($end->toDateString() < today()->toDateString()) {
            return 'expired';
        }

        if ($end->between($now->copy()->startOfDay(), $sevenDaysFromNow->copy()->endOfDay())) {
            return 'expiring';
        }

        return 'active';
    }
}
