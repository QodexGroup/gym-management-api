<?php

namespace App\Services\Core;

use App\Constant\ClassTypeScheduleConstant;
use App\Constant\CustomerMembershipConstant;
use App\Data\CoachDashboardStats;
use App\Data\CoachPtClients;
use App\Data\DashboardStats;
use App\Data\UpcomingGroupSessions;
use App\Data\UpcomingPtSessions;
use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use App\Repositories\Account\ClassScheduleSessionRepository;
use App\Repositories\Core\ClassSessionBookingRepository;
use App\Repositories\Core\CustomerMembershipRepository;
use App\Repositories\Core\CustomerPaymentRepository;
use App\Repositories\Core\CustomerRepository;
use App\Repositories\Core\PtBookingRepository;
use Carbon\Carbon;

class DashboardService
{
    public function __construct(
        private CustomerRepository $customerRepository,
        private CustomerMembershipRepository $membershipRepository,
        private CustomerPaymentRepository $customerPaymentRepository,
        private ClassScheduleSessionRepository $sessionRepository,
        private ClassSessionBookingRepository $bookingRepository,
        private PtBookingRepository $ptBookingRepository,
    ) {
    }

    /**
     * @param int $accountId
     *
     * @return DashboardStats
     */
    public function getStats(int $accountId): DashboardStats
    {
        $stats = new DashboardStats();
        $stats->totalMembers = $this->customerRepository->countByAccountId($accountId);
        $stats->activeMembers = $this->customerRepository->countWithActiveMembership($accountId);
        $stats->newRegistrations = $this->customerRepository->countRegisteredBetween($accountId);
        $stats->todayRevenue = $this->customerPaymentRepository->getTodayRevenueByAccount($accountId);
        $stats->expiringMemberships = $this->membershipRepository->countExpiringMemberships($accountId);
        $stats->expiringMembersList = $this->buildExpiringMembersList($accountId);
        $stats->membershipDistribution = $this->membershipRepository->getMembershipDistributionByPlan($accountId);

        return $stats;
    }

    /**
     * @param int $accountId
     *
     * @return CoachDashboardStats
     */
    public function getCoachDashboardStats(int $accountId): CoachDashboardStats
    {
        $stats = new CoachDashboardStats();
        $stats->expiringMembersList = $this->buildExpiringMembersList($accountId);

        return $stats;
    }

    /**
     * @param int $accountId
     * @param int|null $coachUserId
     * @param bool $scopeToCoach
     *
     * @return UpcomingGroupSessions
     */
    public function getUpcomingGroupSessions(int $accountId, ?int $coachUserId, bool $scopeToCoach): UpcomingGroupSessions
    {
        $result = new UpcomingGroupSessions();
        $sessions = $this->sessionRepository->getUpcomingSessionsFromToday(
            $accountId, $coachUserId, $scopeToCoach, ClassTypeScheduleConstant::GROUP_CLASS
        );

        if ($sessions->isEmpty()) {
            $result->sessions = [];
            return $result;
        }

        $bookingsBySession = $this->bookingRepository
            ->getActiveBookingsBySessionIds($accountId, $sessions->pluck('id')->toArray())
            ->groupBy(fn ($b) => (int) $b->class_schedule_session_id);

        $result->sessions = [];
        foreach ($sessions as $session) {
            $schedule = $session->classSchedule;
            if (!$schedule) {
                continue;
            }

            $participants = [];
            foreach ($bookingsBySession->get((int) $session->id, collect()) as $b) {
                if ($b->customer) {
                    $participants[] = ['customerId' => $b->customer->id, 'name' => $b->customer->full_name];
                }
            }

            $coach = $schedule->coach;
            $result->sessions[] = [
                'id' => $session->id,
                'startTime' => $session->start_time,
                'endTime' => $session->end_time,
                'className' => $schedule->class_name,
                'classType' => $schedule->class_type,
                'coach' => $coach ? ['id' => $coach->id, 'firstname' => $coach->firstname, 'lastname' => $coach->lastname, 'fullName' => $coach->full_name] : null,
                'participants' => $participants,
            ];
        }

        return $result;
    }

    public function getUpcomingPtSessions(int $accountId, ?int $coachUserId, bool $scopeToCoach): UpcomingPtSessions
    {
        $result = new UpcomingPtSessions();
        $sessions = $this->sessionRepository->getUpcomingSessionsFromToday(
            $accountId, $coachUserId, $scopeToCoach, ClassTypeScheduleConstant::PERSONAL_TRAINING
        );

        if ($sessions->isEmpty()) {
            $result->sessions = [];
            return $result;
        }

        $scheduleIds = $sessions->pluck('class_schedule_id')->unique()->filter()->values();
        $ptDateMin = $sessions->min('start_time');
        $ptDateMax = $sessions->max('start_time');
        $bookingFrom = $ptDateMin ? Carbon::parse($ptDateMin)->subDay()->startOfDay()->toDateString() : Carbon::now()->subDay()->toDateString();
        $bookingTo = $ptDateMax ? Carbon::parse($ptDateMax)->addDay()->endOfDay()->toDateString() : Carbon::now()->addDays(31)->toDateString();

        $ptBookings = $this->ptBookingRepository->getActiveBookingsByScheduleAndDateRange(
            $accountId, $scheduleIds->toArray(), $bookingFrom, $bookingTo
        );

        $byKey = $ptBookings->groupBy(fn ($pb) => (int) $pb->class_schedule_id.'|'.Carbon::parse($pb->booking_date)->format('Y-m-d').'|'.(int) $pb->coach_id);
        $byDate = $ptBookings->groupBy(fn ($pb) => (int) $pb->class_schedule_id.'|'.Carbon::parse($pb->booking_date)->format('Y-m-d'));

        $result->sessions = [];
        foreach ($sessions as $session) {
            $schedule = $session->classSchedule;
            if (!$schedule) {
                continue;
            }

            $sessionDate = $session->start_time->format('Y-m-d');
            $ptKey = (int) $schedule->id.'|'.$sessionDate.'|'.(int) $schedule->coach_id;
            $matched = $byKey->get($ptKey, collect());
            if ($matched->isEmpty()) {
                $matched = $byDate->get((int) $schedule->id.'|'.$sessionDate, collect());
            }

            $participants = [];
            foreach ($matched as $pb) {
                if ($pb->customer) {
                    $participants[] = ['customerId' => $pb->customer->id, 'name' => $pb->customer->full_name];
                }
            }

            $coach = $schedule->coach;
            $result->sessions[] = [
                'id' => $session->id,
                'startTime' => $session->start_time,
                'endTime' => $session->end_time,
                'className' => $schedule->class_name,
                'classType' => $schedule->class_type,
                'coach' => $coach ? ['id' => $coach->id, 'firstname' => $coach->firstname, 'lastname' => $coach->lastname, 'fullName' => $coach->full_name] : null,
                'participants' => $participants,
            ];
        }

        return $result;
    }

    /**
     * @param int $accountId
     * @param int $coachId
     *
     * @return CoachPtClients
     */
    public function getCoachAssignedPtClients(int $accountId, int $coachId): CoachPtClients
    {
        $total = $this->customerRepository->countCoachPtClients($accountId, $coachId);
        $customers = $this->customerRepository->getCoachPtClients($accountId, $coachId);

        $now = Carbon::now();
        $sevenDaysFromNow = $now->copy()->addDays(7);

        $members = $customers->map(function (Customer $c) use ($now, $sevenDaysFromNow) {
            $m = $c->currentMembership;

            return [
                'id' => $c->id,
                'name' => $c->full_name,
                'firstName' => $c->first_name,
                'lastName' => $c->last_name,
                'email' => $c->email,
                'photo' => $c->photo,
                'membership' => $m && $m->membershipPlan ? $m->membershipPlan->plan_name : '—',
                'membershipStatus' => $this->deriveMembershipDisplayStatus($m, $now, $sevenDaysFromNow),
            ];
        })->toArray();

        $result = new CoachPtClients();
        $result->members = $members;
        $result->total = $total;

        return $result;
    }

    /**
     * @param int $accountId
     * @param Carbon $now
     * @param Carbon $sevenDaysFromNow
     *
     * @return array
     */
    private function buildExpiringMembersList(int $accountId): array
    {
        $now = Carbon::now();
        $sevenDaysFromNow = $now->copy()->addDays(7);

        return $this->membershipRepository->getExpiringMemberships($accountId, $now, $sevenDaysFromNow)
            ->map(function ($membership) use ($now) {
                $daysUntilExpiry = $now->diffInDays($membership->membership_end_date, false);

                if ($membership->membership_end_date->toDateString() < today()->toDateString()) {
                    $status = CustomerMembershipConstant::STATUS_EXPIRED;
                } elseif ($daysUntilExpiry <= 7) {
                    $status = CustomerMembershipConstant::STATUS_EXPIRING;
                } else {
                    $status = CustomerMembershipConstant::STATUS_ACTIVE;
                }

                return [
                    'id' => $membership->customer->id,
                    'name' => $membership->customer->full_name,
                    'email' => $membership->customer->email,
                    'membership' => $membership->membershipPlan->plan_name ?? 'N/A',
                    'membershipExpiry' => $membership->membership_end_date->format('Y-m-d'),
                    'membershipStatus' => $status,
                    'avatar' => $membership->customer->photo ?? null,
                ];
            })->toArray();
    }

    /**
     * @param CustomerMembership|null $membership
     * @param Carbon $now
     * @param Carbon $sevenDaysFromNow
     *
     * @return string
     */
    private function deriveMembershipDisplayStatus(?CustomerMembership $membership, Carbon $now, Carbon $sevenDaysFromNow): string
    {
        if (!$membership || $membership->status !== CustomerMembershipConstant::STATUS_ACTIVE) {
            return CustomerMembershipConstant::STATUS_EXPIRED;
        }

        $end = $membership->membership_end_date
            ? Carbon::parse($membership->membership_end_date)->copy()->startOfDay()
            : null;

        if (!$end || $end->toDateString() < today()->toDateString()) {
            return CustomerMembershipConstant::STATUS_EXPIRED;
        }

        if ($end->between($now->copy()->startOfDay(), $sevenDaysFromNow->copy()->endOfDay())) {
            return CustomerMembershipConstant::STATUS_EXPIRING;
        }

        return CustomerMembershipConstant::STATUS_ACTIVE;
    }
}
