<?php

namespace Tests\Feature;

use App\Constant\CustomerBillConstant;
use App\Mail\CustomerRegistrationMail;
use App\Mail\MembershipExpiringMail;
use App\Mail\PaymentConfirmationMail;
use App\Models\Account\MembershipPlan;
use App\Models\Core\Customer;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use App\Models\Core\CustomerPayment;
use App\Services\Core\CustomerPaymentService;
use App\Services\Core\CustomerService;
use App\Services\Core\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;
    protected MembershipPlan $membershipPlan;
    protected NotificationService $notificationService;
    protected CustomerPaymentService $paymentService;
    protected CustomerService $customerService;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake mail to prevent actual email sending during tests
        Mail::fake();

        // Create test membership plan
        $this->membershipPlan = MembershipPlan::create([
            'account_id' => 1,
            'plan_name' => 'Monthly Plan',
            'price' => 1000.00,
            'plan_period' => 1,
            'plan_interval' => 'months',
            'features' => [],
        ]);

        // Create test customer with email
        $this->customer = Customer::create([
            'account_id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone_number' => '1234567890',
            'balance' => 0,
        ]);

        // Initialize services
        $this->notificationService = app(NotificationService::class);
        $this->paymentService = app(CustomerPaymentService::class);
        $this->customerService = app(CustomerService::class);
    }

    /**
     * Test that payment confirmation email is sent when payment is received
     */
    public function test_payment_confirmation_email_is_sent(): void
    {
        // Create a bill
        $bill = CustomerBill::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->membershipPlan->id,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_date' => Carbon::now(),
            'gross_amount' => 1000.00,
            'discount_percentage' => 0,
            'net_amount' => 1000.00,
            'paid_amount' => 0,
            'bill_status' => CustomerBillConstant::BILL_STATUS_ACTIVE,
        ]);

        // Create a payment
        $payment = CustomerPayment::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'customer_bill_id' => $bill->id,
            'amount' => 1000.00,
            'payment_method' => 'cash',
            'payment_date' => Carbon::now(),
            'reference_number' => 'REF123',
        ]);

        // Trigger payment notification
        $this->notificationService->createPaymentReceivedNotification($payment);

        // Assert that payment confirmation email was sent
        Mail::assertSent(PaymentConfirmationMail::class, function ($mail) {
            return $mail->customer->id === $this->customer->id
                && $mail->hasTo($this->customer->email);
        });
    }

    /**
     * Test that membership expiring email is sent
     */
    public function test_membership_expiring_email_is_sent(): void
    {
        // Create a membership that expires soon
        $membership = CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->membershipPlan->id,
            'membership_start_date' => Carbon::now()->subMonth(),
            'membership_end_date' => Carbon::now()->addDays(7), // Expires in 7 days
            'status' => 'active',
        ]);

        // Trigger membership expiring notification
        $this->notificationService->createMembershipExpiringNotification($membership);

        // Assert that membership expiring email was sent
        Mail::assertSent(MembershipExpiringMail::class, function ($mail) {
            return $mail->customer->id === $this->customer->id
                && $mail->membership->id === $membership->id
                && $mail->hasTo($this->customer->email);
        });
    }

    /**
     * Test that customer registration email is sent
     */
    public function test_customer_registration_email_is_sent(): void
    {
        // Create a new customer
        $newCustomer = Customer::create([
            'account_id' => 1,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
            'phone_number' => '0987654321',
            'balance' => 0,
        ]);

        // Create a membership for the customer
        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $newCustomer->id,
            'membership_plan_id' => $this->membershipPlan->id,
            'membership_start_date' => Carbon::now(),
            'membership_end_date' => Carbon::now()->addMonth(),
            'status' => 'active',
        ]);

        // Trigger customer registration notification
        $this->notificationService->createCustomerRegisteredNotification($newCustomer);

        // Assert that customer registration email was sent
        Mail::assertSent(CustomerRegistrationMail::class, function ($mail) use ($newCustomer) {
            return $mail->customer->id === $newCustomer->id
                && $mail->hasTo($newCustomer->email);
        });
    }

    /**
     * Test that email is not sent when customer has no email address
     */
    public function test_email_not_sent_when_customer_has_no_email(): void
    {
        // Create customer without email
        $customerWithoutEmail = Customer::create([
            'account_id' => 1,
            'first_name' => 'No',
            'last_name' => 'Email',
            'email' => null,
            'phone_number' => '1234567890',
            'balance' => 0,
        ]);

        // Create a bill and payment
        $bill = CustomerBill::create([
            'account_id' => 1,
            'customer_id' => $customerWithoutEmail->id,
            'membership_plan_id' => $this->membershipPlan->id,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_date' => Carbon::now(),
            'gross_amount' => 1000.00,
            'discount_percentage' => 0,
            'net_amount' => 1000.00,
            'paid_amount' => 0,
            'bill_status' => CustomerBillConstant::BILL_STATUS_ACTIVE,
        ]);

        $payment = CustomerPayment::create([
            'account_id' => 1,
            'customer_id' => $customerWithoutEmail->id,
            'customer_bill_id' => $bill->id,
            'amount' => 1000.00,
            'payment_method' => 'cash',
            'payment_date' => Carbon::now(),
        ]);

        // Clear any previous mail assertions
        Mail::fake();

        // Trigger payment notification
        $this->notificationService->createPaymentReceivedNotification($payment);

        // Assert that no email was sent
        Mail::assertNothingSent();
    }

    /**
     * Test that payment confirmation email contains correct data
     */
    public function test_payment_confirmation_email_contains_correct_data(): void
    {
        // Create a bill
        $bill = CustomerBill::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->membershipPlan->id,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_date' => Carbon::now(),
            'gross_amount' => 1500.00,
            'discount_percentage' => 0,
            'net_amount' => 1500.00,
            'paid_amount' => 0,
            'bill_status' => CustomerBillConstant::BILL_STATUS_ACTIVE,
        ]);

        // Create a payment
        $payment = CustomerPayment::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'customer_bill_id' => $bill->id,
            'amount' => 1500.00,
            'payment_method' => 'credit_card',
            'payment_date' => Carbon::now(),
            'reference_number' => 'PAY-12345',
        ]);

        // Trigger payment notification
        $this->notificationService->createPaymentReceivedNotification($payment);

        // Assert email was sent with correct data
        Mail::assertSent(PaymentConfirmationMail::class, function ($mail) use ($payment) {
            return $mail->payment->id === $payment->id
                && $mail->payment->amount == 1500.00
                && $mail->payment->payment_method === 'credit_card'
                && $mail->hasTo($this->customer->email);
        });
    }
}
