<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copy per-account rows from tb_notification_preferences into the generic
     * account_system_settings EAV store (notify_* keys), so existing choices
     * survive the move of notification settings into System Settings.
     *
     * The old table is intentionally kept for now (rollback safety) and can be
     * dropped in a later cleanup migration.
     */
    public function up(): void
    {
        if (!Schema::hasTable('tb_notification_preferences') || !Schema::hasTable('account_system_settings')) {
            return;
        }

        $map = [
            'membership_expiry_enabled' => 'notify_membership_expiry',
            'payment_alerts_enabled' => 'notify_payment_received',
            'new_registrations_enabled' => 'notify_new_registration',
        ];

        $now = now();

        DB::table('tb_notification_preferences')
            // account_system_settings has a FK to accounts; skip orphaned pref rows.
            ->whereIn('account_id', DB::table('accounts')->select('id'))
            ->orderBy('account_id')
            ->chunk(100, function ($prefs) use ($map, $now) {
            $rows = [];
            foreach ($prefs as $pref) {
                foreach ($map as $column => $setKey) {
                    $rows[] = [
                        'account_id' => $pref->account_id,
                        'set_key' => $setKey,
                        'set_value' => ((bool) $pref->{$column}) ? '1' : '0',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (!empty($rows)) {
                DB::table('account_system_settings')->upsert($rows, ['account_id', 'set_key'], ['set_value', 'updated_at']);
            }
        });
    }

    public function down(): void
    {
        DB::table('account_system_settings')->whereIn('set_key', [
            'notify_membership_expiry',
            'notify_payment_received',
            'notify_new_registration',
        ])->delete();
    }
};
