<?php

namespace App\Console\Commands;

use App\Constant\CustomerBillConstant;
use App\Mail\CustomerRegistrationMail;
use App\Mail\MembershipExpiringMail;
use App\Mail\PaymentConfirmationMail;
use App\Models\Account\MembershipPlan;
use App\Models\Core\Customer;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use App\Models\Core\CustomerPayment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmailNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {type : The type of email to test (payment|membership|registration)} {--email= : Customer email address (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email notifications by sending a sample email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type');
        $email = $this->option('email') ?? 'test@example.com';

        // Create or get test customer
        $customer = Customer::where('email', $email)->first();
        
        if (!$customer) {
            $customer = Customer::create([
                'account_id' => 1,
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => $email,
                'phone_number' => '1234567890',
                'balance' => 0,
            ]);
            $this->info("Created test customer: {$email}");
        }

        switch ($type) {
            case 'payment':
                $this->testPaymentEmail($customer);
                break;
            case 'membership':
                $this->testMembershipExpiringEmail($customer);
                break;
            case 'registration':
                $this->testRegistrationEmail($customer);
                break;
            default:
                $this->error("Invalid type. Use: payment, membership, or registration");
                return Command::FAILURE;
        }

        $this->info("✓ Test email sent successfully to {$email}");
        $this->info("Check your email inbox or MailerSend dashboard to verify.");

        return Command::SUCCESS;
    }

    /**
     * Test payment confirmation email
     */
    private function testPaymentEmail(Customer $customer): void
    {
        // Get or create membership plan
        $membershipPlan = MembershipPlan::where('account_id', 1)->first();
        
        if (!$membershipPlan) {
            $membershipPlan = MembershipPlan::create([
                'account_id' => 1,
                'plan_name' => 'Test Monthly Plan',
                'price' => 1000.00,
                'plan_period' => 1,
                'plan_interval' => 'months',
                'features' => [],
            ]);
        }

        // Create test bill
        $bill = CustomerBill::create([
            'account_id' => 1,
            'customer_id' => $customer->id,
            'membership_plan_id' => $membershipPlan->id,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_date' => Carbon::now(),
            'gross_amount' => 1000.00,
            'discount_percentage' => 0,
            'net_amount' => 1000.00,
            'paid_amount' => 0,
            'bill_status' => CustomerBillConstant::BILL_STATUS_ACTIVE,
        ]);

        // Create test payment
        $payment = CustomerPayment::create([
            'account_id' => 1,
            'customer_id' => $customer->id,
            'customer_bill_id' => $bill->id,
            'amount' => 1000.00,
            'payment_method' => 'cash',
            'payment_date' => Carbon::now(),
            'reference_number' => 'TEST-' . time(),
        ]);

        // Send email
        Mail::to($customer->email)->send(new PaymentConfirmationMail($customer, $payment));
    }

    /**
     * Test membership expiring email
     */
    private function testMembershipExpiringEmail(Customer $customer): void
    {
        // Get or create membership plan
        $membershipPlan = MembershipPlan::where('account_id', 1)->first();
        
        if (!$membershipPlan) {
            $membershipPlan = MembershipPlan::create([
                'account_id' => 1,
                'plan_name' => 'Test Monthly Plan',
                'price' => 1000.00,
                'plan_period' => 1,
                'plan_interval' => 'months',
                'features' => [],
            ]);
        }

        // Create test membership expiring soon
        $membership = CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $customer->id,
            'membership_plan_id' => $membershipPlan->id,
            'membership_start_date' => Carbon::now()->subMonth(),
            'membership_end_date' => Carbon::now()->addDays(7), // Expires in 7 days
            'status' => 'active',
        ]);

        // Send email
        Mail::to($customer->email)->send(new MembershipExpiringMail($customer, $membership));
    }

    /**
     * Test customer registration email
     */
    private function testRegistrationEmail(Customer $customer): void
    {
        // Send email
        Mail::to($customer->email)->send(new CustomerRegistrationMail($customer));
    }
}
