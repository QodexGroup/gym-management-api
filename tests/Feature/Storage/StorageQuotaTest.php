<?php

namespace Tests\Feature\Storage;

use App\Constant\AccountStatusConstant;
use App\Constant\ResourceKeyConstant;
use App\Constant\StorageConstant;
use App\Exceptions\QuotaExceededException;
use App\Models\Account\Account;
use App\Models\Account\AccountUsage;
use App\Services\Account\AccountUsageService;
use App\Services\Core\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Storage quota metering: limits, counter movement, the upload guard, and the
 * reconcile safeguard. R2 is unset in phpunit.xml, so these run fully offline.
 */
class StorageQuotaTest extends TestCase
{
    use RefreshDatabase;

    private StorageService $storageService;
    private AccountUsageService $accountUsageService;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        // Force R2 "unconfigured" so these tests never make network calls, regardless
        // of the container's env or a cached config.
        config()->set('filesystems.disks.r2.bucket', '');
        config()->set('filesystems.disks.r2.key', '');
        config()->set('filesystems.disks.r2.secret', '');

        $this->storageService = app(StorageService::class);
        $this->accountUsageService = app(AccountUsageService::class);

        $this->account = Account::create([
            'account_name' => 'Storage Gym',
            'account_email' => 'storage' . uniqid() . '@example.com',
            'account_phone' => '1234567890',
            'status' => AccountStatusConstant::STATUS_ACTIVE,
        ]);
    }

    private function usedKb(): float
    {
        return $this->storageService->getUsage($this->account->id)->usedKb;
    }

    public function test_new_account_starts_empty_on_the_default_five_gb_limit(): void
    {
        $usage = $this->storageService->getUsage($this->account->id);

        $this->assertEqualsWithDelta(StorageConstant::DEFAULT_STORAGE_LIMIT_KB, $usage->limitKb, 0.01);
        $this->assertEqualsWithDelta(0.0, $usage->usedKb, 0.01);
        $this->assertFalse($usage->isFull);
        $this->assertFalse($usage->isNearLimit);
    }

    public function test_increment_and_decrement_track_usage(): void
    {
        $this->storageService->incrementUsage($this->account->id, 2048); // 2 MB
        $this->assertEqualsWithDelta(2048.0, $this->usedKb(), 0.01);

        $this->storageService->decrementUsage($this->account->id, 1024);
        $this->assertEqualsWithDelta(1024.0, $this->usedKb(), 0.01);
    }

    public function test_decrement_never_drops_below_zero(): void
    {
        $this->storageService->incrementUsage($this->account->id, 100);
        $this->storageService->decrementUsage($this->account->id, 500);

        $this->assertEqualsWithDelta(0.0, $this->usedKb(), 0.01);
    }

    public function test_upload_is_blocked_when_it_would_exceed_the_quota(): void
    {
        $this->accountUsageService->setUsed(
            $this->account->id,
            ResourceKeyConstant::STORAGE,
            StorageConstant::DEFAULT_STORAGE_LIMIT_KB - 100, // only 100 KB headroom
        );

        $this->expectException(QuotaExceededException::class);

        $this->storageService->assertCanUpload($this->account->id, 500);
    }

    public function test_upload_is_allowed_within_the_quota(): void
    {
        $this->accountUsageService->setUsed(
            $this->account->id,
            ResourceKeyConstant::STORAGE,
            StorageConstant::DEFAULT_STORAGE_LIMIT_KB - 1000,
        );

        $this->storageService->assertCanUpload($this->account->id, 500);

        $this->assertEqualsWithDelta(
            StorageConstant::DEFAULT_STORAGE_LIMIT_KB - 1000,
            $this->usedKb(),
            0.01,
        );
    }

    public function test_per_account_limit_override_beats_the_config_default(): void
    {
        AccountUsage::updateOrCreate(
            ['account_id' => $this->account->id, 'resource_key' => ResourceKeyConstant::STORAGE],
            ['used_amount' => 0, 'limit_override' => 1024],
        );

        $this->assertEqualsWithDelta(1024.0, $this->storageService->getUsage($this->account->id)->limitKb, 0.01);
    }

    public function test_near_limit_flag_trips_at_the_warning_threshold(): void
    {
        $this->accountUsageService->setUsed(
            $this->account->id,
            ResourceKeyConstant::STORAGE,
            StorageConstant::DEFAULT_STORAGE_LIMIT_KB * 0.95,
        );

        $usage = $this->storageService->getUsage($this->account->id);

        $this->assertTrue($usage->isNearLimit);
        $this->assertFalse($usage->isFull);
    }

    /**
     * Regression guard: the DB sum covers only tb_customer_files (avatars and
     * receipts store a path with no size), so when R2 is unreachable the reconcile
     * must leave the counter alone rather than overwrite it with an undercount.
     */
    public function test_reconcile_does_not_overwrite_counter_when_r2_unavailable(): void
    {
        $this->storageService->incrementUsage($this->account->id, 4096); // 4 MB of avatars/receipts

        $usage = $this->storageService->recalculateForAccount($this->account);

        $this->assertEqualsWithDelta(4096.0, $usage->usedKb, 0.01);
        $this->assertEqualsWithDelta(4096.0, $this->usedKb(), 0.01);
    }

    /**
     * Subscription/reactivation receipts are proof-of-payment to the platform,
     * not the gym's own data, so they never count toward the quota — the reconcile
     * must skip them the same way the live counter never increments for them.
     */
    public function test_subscription_receipts_are_exempt_from_the_quota(): void
    {
        $id = $this->account->id;

        $this->assertTrue($this->storageService->isQuotaExempt($id, $id . '/subscription-receipts/receipt_1.png'));

        // Everything the gym actually owns still counts.
        $this->assertFalse($this->storageService->isQuotaExempt($id, $id . '/receipt/expense_1.png'));
        $this->assertFalse($this->storageService->isQuotaExempt($id, $id . '/userprofile/avatar_1.png'));
        $this->assertFalse($this->storageService->isQuotaExempt($id, $id . '/42/scan_1.png'));
    }

    public function test_size_resolution_falls_back_to_client_value_without_r2(): void
    {
        $this->assertEqualsWithDelta(
            250.0,
            $this->storageService->resolveSizeKb('1/customer/photo.png', 250.0),
            0.01,
        );

        // No path → always the reported value.
        $this->assertEqualsWithDelta(80.0, $this->storageService->resolveSizeKb(null, 80.0), 0.01);
    }
}
