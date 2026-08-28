<?php

namespace App\Services\Public;

use App\Data\PublicGymRegistrationInfo;
use App\Helpers\PhoneNumberHelper;
use App\Models\Account\Account;
use App\Models\Account\MembershipPlan;
use App\Models\Core\Customer;
use App\Repositories\Account\MembershipPlanRepository;
use App\Repositories\Core\CustomerBillRepository;
use App\Repositories\Core\CustomerRepository;
use App\Services\Account\AccountSystemSettingService;
use App\Services\Core\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Business logic for the public /join/{publicCode} registration flow.
 *
 * Deliberately does NOT go through CustomerService or GenericData. GenericData
 * carries an authenticated request — its user, filters, sorts and paging — and
 * none of that exists here: the account comes from the URL's public_code, and
 * there is no acting user at all. Routing this flow through it meant faking or
 * null-guarding `userData` at every layer, which is how a crafted request ends
 * up written against the wrong account.
 *
 * Instead this composes the repository methods that already take explicit
 * arguments, the same shape used by the import flow — the other place customers
 * are created with no signed-in user.
 */
class PublicRegistrationService
{
    /**
     * @param CustomerRepository $customerRepository
     * @param CustomerBillRepository $customerBillRepository
     * @param MembershipPlanRepository $membershipPlanRepository
     * @param AccountSystemSettingService $settingService
     * @param NotificationService $notificationService
     */
    public function __construct(
        private CustomerRepository $customerRepository,
        private CustomerBillRepository $customerBillRepository,
        private MembershipPlanRepository $membershipPlanRepository,
        private AccountSystemSettingService $settingService,
        private NotificationService $notificationService,
    ) {
    }

    /**
     * Build the public-safe description of a gym for the registration page.
     *
     * @param Account $account
     * @return PublicGymRegistrationInfo
     */
    public function getGymRegistrationInfo(Account $account): PublicGymRegistrationInfo
    {
        $settings = $this->settingService->getForAccount($account->id);

        $info = new PublicGymRegistrationInfo();
        $info->gymName = (string) $account->account_name;
        $info->welcomeText = (string) ($settings['kioskRegistrationWelcomeText'] ?? '');
        $info->successText = (string) ($settings['kioskRegistrationSuccessText'] ?? '');
        $info->requireEmail = (bool) ($settings['kioskRegistrationRequireEmail'] ?? false);
        $info->requireAddress = (bool) ($settings['kioskRegistrationRequireAddress'] ?? false);
        $info->requireEmergencyContact = (bool) ($settings['kioskRegistrationRequireEmergencyContact'] ?? false);
        $info->membershipPlans = $this->membershipPlanRepository->getPlansForAccount($account->id)
            ->map(fn (MembershipPlan $plan) => [
                'id' => $plan->id,
                'planName' => $plan->plan_name,
                'price' => $plan->price,
                'planPeriod' => $plan->plan_period,
                'planInterval' => $plan->plan_interval,
            ])
            ->all();

        return $info;
    }

    /**
     * Create a member from a public submission, with an optional membership.
     *
     * Duplicate phone numbers are rejected outright, per an explicit product
     * decision. The trade-off accepted with it: this makes the endpoint an
     * oracle for "is this number a member of this gym?". The per-IP and per-gym
     * rate limits and the logging below are the mitigation.
     *
     * @param Account $account Resolved from the URL public_code.
     * @param array<string, mixed> $validated Validated camelCase input.
     * @param string $ipAddress Caller IP, recorded on rejection for abuse review.
     * @return Customer
     * @throws RuntimeException Duplicate phone, or a plan that is not this gym's (both render as 422).
     */
    public function createRegistration(Account $account, array $validated, string $ipAddress): Customer
    {
        $normalizedPhone = PhoneNumberHelper::normalize($validated['phoneNumber'] ?? null);

        if ($this->customerRepository->existsByPhoneNumber($account->id, $normalizedPhone)) {
            Log::info('Public registration rejected: duplicate phone number', [
                'account_id' => $account->id,
                'phone_number' => $normalizedPhone,
                'ip' => $ipAddress,
            ]);

            throw new RuntimeException(
                'This phone number is already registered at this gym. Please see the front desk for assistance.'
            );
        }

        $plan = $this->resolvePlan($account, $validated['membershipPlanId'] ?? null);
        $startDate = $this->resolveStartDate($validated['membershipStartDate'] ?? null);

        $customer = DB::transaction(function () use ($account, $validated, $normalizedPhone, $plan, $startDate) {
            $customer = $this->customerRepository->createFromPublicRegistration(
                $account->id,
                $this->buildCustomerAttributes($validated, $normalizedPhone, $plan)
            );

            if ($plan !== null) {
                $this->customerRepository->createMembership($account->id, $customer->id, $plan, $startDate);

                // createAutomatedBill() is the existing "no acting user" bill
                // constructor — it leaves created_by/updated_by unset, which is
                // the honest record for a bill a member raised themselves.
                $this->customerBillRepository->createAutomatedBill(
                    $account->id,
                    $customer->id,
                    $plan->id,
                    (float) $plan->price,
                    $startDate ?? Carbon::now()
                );
            }

            return $customer;
        });

        $this->notificationService->createCustomerRegisteredNotification($customer);

        Log::info('Public registration created', [
            'account_id' => $account->id,
            'customer_id' => $customer->id,
            'membership_plan_id' => $plan?->id,
            'ip' => $ipAddress,
        ]);

        return $customer;
    }

    /**
     * Map the validated input onto customer columns.
     *
     * An explicit allowlist: every column the member may set is named here, so
     * staff-only fields (photo, qr_code_uuid, trainer) cannot be reached even if
     * validation later grows looser. Balance mirrors what the authenticated
     * create does — the plan's price when one is chosen.
     *
     * @param array<string, mixed> $validated
     * @param string $normalizedPhone
     * @param MembershipPlan|null $plan
     * @return array<string, mixed>
     */
    private function buildCustomerAttributes(array $validated, string $normalizedPhone, ?MembershipPlan $plan): array
    {
        $optional = [
            'gender' => 'gender',
            'email' => 'email',
            'address' => 'address',
            'emergencyContactName' => 'emergency_contact_name',
            'emergencyContactPhone' => 'emergency_contact_phone',
            'emergencyContactRelationship' => 'emergency_contact_relationship',
            'bloodType' => 'blood_type',
            'allergies' => 'allergies',
            'medicalConditions' => 'medical_conditions',
            'medicalNotes' => 'medical_notes',
        ];

        $attributes = [
            'first_name' => $validated['firstName'],
            'last_name' => $validated['lastName'],
            'date_of_birth' => $validated['dateOfBirth'],
            'phone_number' => $normalizedPhone,
            'balance' => $plan !== null ? $plan->price : 0,
        ];

        foreach ($optional as $inputKey => $column) {
            $value = $validated[$inputKey] ?? null;
            $attributes[$column] = is_string($value) && trim($value) === '' ? null : $value;
        }

        return $attributes;
    }

    /**
     * Resolve the chosen membership plan, scoped to this gym.
     *
     * Looking it up against the gym's own plans is the tenant check: a crafted
     * id belonging to another account simply is not found, and the member gets
     * a clean 422 instead of the bare ModelNotFoundException that
     * findMembershipPlanById() would raise.
     *
     * @param Account $account
     * @param int|string|null $membershipPlanId
     * @return MembershipPlan|null Null when no plan was chosen.
     * @throws RuntimeException When the plan is not one of this gym's.
     */
    private function resolvePlan(Account $account, int|string|null $membershipPlanId): ?MembershipPlan
    {
        if (empty($membershipPlanId)) {
            return null;
        }

        $plan = $this->membershipPlanRepository->getPlansForAccount($account->id)
            ->firstWhere('id', (int) $membershipPlanId);

        if (!$plan) {
            throw new RuntimeException('That membership plan is not available at this gym.');
        }

        return $plan;
    }

    /**
     * Parse the requested membership start date, defaulting to today.
     *
     * @param string|null $startDate
     * @return Carbon|null Null starts the membership today.
     */
    private function resolveStartDate(?string $startDate): ?Carbon
    {
        return empty($startDate) ? null : Carbon::parse($startDate)->startOfDay();
    }
}
