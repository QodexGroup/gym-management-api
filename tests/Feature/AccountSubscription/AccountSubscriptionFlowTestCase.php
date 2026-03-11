<?php

namespace Tests\Feature\AccountSubscription;

use App\Constant\AccountInvoiceStatusConstant;
use App\Constant\AccountInvoiceTypeConstant;
use App\Constant\AccountPaymentRequestStatusConstant;
use App\Constant\AccountStatusConstant;
use App\Helpers\GenericData;
use App\Models\Account\Account;
use App\Models\Account\AccountInvoice;
use App\Models\Account\AccountPaymentRequest;
use App\Models\Account\AccountSubscriptionPlan;
use App\Models\Account\SubscriptionPlan;
use App\Models\User;
use App\Repositories\Account\AccountSubscription\AccountInvoiceRepository;
use App\Repositories\Account\AccountSubscription\AccountPaymentRequestRepository;
use App\Repositories\Account\AccountSubscription\AccountSubscriptionPlanRepository;
use App\Repositories\Account\AccountSubscription\SubscriptionPlanRepository;
use App\Services\Account\AccountSubscription\AccountPaymentRequestService;
use App\Services\Account\AccountSubscription\BillingLifecycleService;
use App\Services\Admin\AdminPaymentRequestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AccountSubscriptionFlowTestCase extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionPlan $trialPlan;
    protected BillingLifecycleService $billingLifecycleService;
    protected AccountPaymentRequestService $accountPaymentRequestService;
    protected AdminPaymentRequestService $adminPaymentRequestService;
    protected AccountInvoiceRepository $accountInvoiceRepository;
    protected AccountPaymentRequestRepository $accountPaymentRequestRepository;
    protected AccountSubscriptionPlanRepository $accountSubscriptionPlanRepository;
    protected SubscriptionPlanRepository $subscriptionPlanRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trialPlan = SubscriptionPlan::create([
            'name' => 'Trial Plan',
            'slug' => 'trial',
            'interval' => null,
            'price' => 0,
            'trial_days' => 7,
            'is_trial' => true,
        ]);

        $this->billingLifecycleService = app(BillingLifecycleService::class);
        $this->accountPaymentRequestService = app(AccountPaymentRequestService::class);
        $this->adminPaymentRequestService = app(AdminPaymentRequestService::class);
        $this->accountInvoiceRepository = app(AccountInvoiceRepository::class);
        $this->accountPaymentRequestRepository = app(AccountPaymentRequestRepository::class);
        $this->accountSubscriptionPlanRepository = app(AccountSubscriptionPlanRepository::class);
        $this->subscriptionPlanRepository = app(SubscriptionPlanRepository::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function createAccount(array $overrides = []): Account
    {
        return Account::create(array_merge([
            'account_name' => 'Test Gym ' . uniqid(),
            'account_email' => 'owner' . uniqid() . '@example.com',
            'account_phone' => '1234567890',
            'status' => AccountStatusConstant::STATUS_ACTIVE,
        ], $overrides));
    }

    protected function createUser(Account $account, array $overrides = []): User
    {
        return User::create(array_merge([
            'account_id' => $account->id,
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => 'user' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => AccountStatusConstant::STATUS_ACTIVE,
        ], $overrides));
    }

    protected function createPlan(array $overrides = []): SubscriptionPlan
    {
        return SubscriptionPlan::create(array_merge([
            'name' => 'Paid Plan ' . uniqid(),
            'slug' => 'plan-' . uniqid(),
            'interval' => 'month',
            'price' => 1000,
            'is_trial' => false,
        ], $overrides));
    }

    protected function createAccountSubscriptionPlan(Account $account, SubscriptionPlan $plan, array $overrides = []): AccountSubscriptionPlan
    {
        return AccountSubscriptionPlan::create(array_merge([
            'account_id' => $account->id,
            'subscription_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'trial_starts_at' => null,
            'trial_ends_at' => null,
            'subscription_starts_at' => Carbon::now()->copy()->subDay(),
            'subscription_ends_at' => null,
        ], $overrides));
    }

    protected function createInvoice(Account $account, ?AccountSubscriptionPlan $plan = null, array $overrides = []): AccountInvoice
    {
        $overrideDetails = $overrides['invoice_details'] ?? null;
        if ($overrideDetails !== null) {
            unset($overrides['invoice_details']);
        }

        $invoiceDetails = $overrideDetails ?? ($plan
            ? $this->buildSubscriptionInvoiceDetails($plan->subscriptionPlan, $plan)
            : ['invoiceType' => AccountInvoiceTypeConstant::TYPE_SUBSCRIPTION]);

        return AccountInvoice::create(array_merge([
            'account_id' => $account->id,
            'account_subscription_plan_id' => $plan?->id,
            'invoice_number' => '#T' . str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
            'billing_period' => BillingLifecycleService::billingPeriodForDate(Carbon::now()),
            'invoice_date' => Carbon::now(),
            'total_amount' => 1000,
            'discount_amount' => 0,
            'status' => AccountInvoiceStatusConstant::STATUS_PENDING,
            'period_from' => Carbon::now()->copy()->day(5),
            'period_to' => Carbon::now()->copy()->day(5)->addMonth()->subDay(),
            'prorate' => 0,
            'invoice_details' => json_encode($invoiceDetails),
        ], $overrides));
    }

    protected function createPaymentRequest(Account $account, User $user, AccountInvoice $invoice, array $overrides = []): AccountPaymentRequest
    {
        return AccountPaymentRequest::create(array_merge([
            'account_id' => $account->id,
            'payment_transaction' => AccountInvoice::class,
            'payment_transaction_id' => $invoice->id,
            'receipt_url' => 'receipts/test-receipt.png',
            'receipt_file_name' => 'test-receipt.png',
            'status' => AccountPaymentRequestStatusConstant::STATUS_PENDING,
            'requested_by' => $user->id,
            'payment_details' => json_encode([
                'invoice_number' => $invoice->invoice_number,
                'total_amount' => $invoice->total_amount,
            ]),
        ], $overrides));
    }

    protected function buildPaymentRequestGenericData(User $user, AccountInvoice $invoice, array $overrides = []): GenericData
    {
        $genericData = new GenericData();
        $genericData->userData = $user;
        $genericData->data = array_merge([
            'invoiceId' => $invoice->id,
            'receiptUrl' => 'receipts/receipt.png',
            'receiptFileName' => 'receipt.png',
        ], $overrides);
        $genericData->syncDataArray();

        return $genericData;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSubscriptionInvoiceDetails(SubscriptionPlan $plan, AccountSubscriptionPlan $asp): array
    {
        return [
            'invoiceType' => AccountInvoiceTypeConstant::TYPE_SUBSCRIPTION,
            'subscriptionPlan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'interval' => $plan->interval,
                'price' => (float) $plan->price,
            ],
            'accountSubscriptionPlan' => [
                'id' => $asp->id,
                'planName' => $asp->plan_name,
                'trialStartsAt' => $asp->trial_starts_at?->toDateString(),
                'trialEndsAt' => $asp->trial_ends_at?->toDateString(),
                'subscriptionStartsAt' => $asp->subscription_starts_at?->toDateString(),
                'subscriptionEndsAt' => $asp->subscription_ends_at?->toDateString(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildReactivationInvoiceDetails(): array
    {
        return [
            'invoiceType' => AccountInvoiceTypeConstant::TYPE_REACTIVATION_FEE,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(?string $json): array
    {
        if (!$json) {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
