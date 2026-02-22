<?php

namespace Tests\Feature\Walkin;

use App\Constants\WalkinCustomerConstant;
use App\Helpers\GenericData;
use App\Models\Common\Walkin;
use App\Models\Common\WalkinCustomer;
use App\Models\Core\Customer;
use App\Models\User;
use App\Services\Common\WalkinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WalkinFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected WalkinService $walkinService;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin time so Carbon::today()/now() produce deterministic values.
        Carbon::setTestNow(Carbon::parse('2026-02-22 10:00:00'));

        $this->user = User::create([
            'account_id' => 1,
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => 'walkin-test@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'account_id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe.walkin@example.com',
            'phone_number' => '1234567890',
            'balance' => 0,
            'qr_code_uuid' => '11111111-1111-1111-1111-111111111111',
        ]);

        $this->walkinService = app(WalkinService::class);
    }

    protected function tearDown(): void
    {
        // Avoid leaking frozen time into other tests in the same PHPUnit process.
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function genericData(array $data): GenericData
    {
        $genericData = new GenericData();
        $genericData->userData = $this->user;
        $genericData->data = $data;
        $genericData->syncDataArray();
        return $genericData;
    }

    public function test_qr_check_in_creates_todays_walkin_and_checks_in_customer(): void
    {
        $walkinCustomer = $this->walkinService->qrCheckIn($this->genericData([
            'uuid' => $this->customer->qr_code_uuid,
        ]));

        $this->assertEquals($this->customer->id, $walkinCustomer->customer_id);
        $this->assertEquals(WalkinCustomerConstant::INSIDE_STATUS, $walkinCustomer->status);
        $this->assertNotNull($walkinCustomer->check_in_time);
        $this->assertNull($walkinCustomer->check_out_time);

        $walkin = Walkin::where('account_id', 1)->whereDate('date', Carbon::today())->first();
        $this->assertNotNull($walkin);

        $dbWalkinCustomer = WalkinCustomer::where('walkin_id', $walkin->id)
            ->where('customer_id', $this->customer->id)
            ->first();
        $this->assertNotNull($dbWalkinCustomer);
    }

    public function test_qr_check_in_twice_throws_customer_already_checked_in(): void
    {
        $this->walkinService->qrCheckIn($this->genericData([
            'uuid' => $this->customer->qr_code_uuid,
        ]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Customer is already checked in');

        $this->walkinService->qrCheckIn($this->genericData([
            'uuid' => $this->customer->qr_code_uuid,
        ]));
    }

    public function test_qr_check_out_updates_status_and_sets_checkout_time(): void
    {
        $checkedIn = $this->walkinService->qrCheckIn($this->genericData([
            'uuid' => $this->customer->qr_code_uuid,
        ]));

        $this->assertEquals(WalkinCustomerConstant::INSIDE_STATUS, $checkedIn->status);
        $this->assertNull($checkedIn->check_out_time);

        $checkedOut = $this->walkinService->qrCheckOut($this->genericData([
            'uuid' => $this->customer->qr_code_uuid,
        ]));

        $this->assertEquals(WalkinCustomerConstant::OUTSIDE_STATUS, $checkedOut->status);
        $this->assertNotNull($checkedOut->check_out_time);
    }

    public function test_qr_check_out_without_walkin_throws_no_session_found(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No walk-in session found for today');

        $this->walkinService->qrCheckOut($this->genericData([
            'uuid' => $this->customer->qr_code_uuid,
        ]));
    }

    public function test_manual_create_walkin_customer_checks_in_and_prevents_duplicates(): void
    {
        $walkin = Walkin::create([
            'account_id' => 1,
            'date' => Carbon::today(),
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ])->fresh();

        $walkinCustomer = $this->walkinService->createWalkinCustomer(
            $walkin->id,
            $this->genericData(['customerId' => $this->customer->id])
        );

        $this->assertEquals($this->customer->id, $walkinCustomer->customer_id);
        $this->assertEquals(WalkinCustomerConstant::INSIDE_STATUS, $walkinCustomer->status);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Walkin customer already exists');

        $this->walkinService->createWalkinCustomer(
            $walkin->id,
            $this->genericData(['customerId' => $this->customer->id])
        );
    }
}
