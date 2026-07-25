<?php

namespace App\Repositories\Core;

use App\Models\Core\NotificationPreference;

class NotificationPreferenceRepository
{
    /**
     * Get notification preferences for an account.
     *
     * @param int $accountId
     * @return NotificationPreference|null
     */
    public function getByAccountId(int $accountId): ?NotificationPreference
    {
        return NotificationPreference::where('account_id', $accountId)->first();
    }

    /**
     * Update or create notification preferences for an account.
     *
     * @param int $accountId
     * @param array $data
     * @return NotificationPreference
     */
    public function updateOrCreate(int $accountId, array $data): NotificationPreference
    {
        return NotificationPreference::updateOrCreate(
            ['account_id' => $accountId],
            $data
        );
    }

    /**
     * Get default preferences if none exist.
     *
     * @return array
     */
    public function getDefaults(): array
    {
        return [
            'membership_expiry_enabled' => true,
            'payment_alerts_enabled' => true,
            'new_registrations_enabled' => true,
        ];
    }
}
