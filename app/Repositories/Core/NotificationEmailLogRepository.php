<?php

namespace App\Repositories\Core;

use App\Models\Core\NotificationEmailLog;

class NotificationEmailLogRepository
{
    /**
     * Check whether an email of this type was already queued for the customer
     * (and optional related record) within the last N hours.
     *
     * @param int $accountId
     * @param string $type e.g. NotificationConstant::TYPE_MEMBERSHIP_EXPIRING
     * @param int $customerId
     * @param int|null $refId related record id (e.g. membership id)
     * @param int $hoursThreshold
     * @return bool
     */
    public function emailExists(int $accountId, string $type, int $customerId, ?int $refId = null, int $hoursThreshold = 24): bool
    {
        $query = NotificationEmailLog::where('account_id', $accountId)
            ->where('type', $type)
            ->where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subHours($hoursThreshold));

        if ($refId !== null) {
            $query->where('ref_id', $refId);
        }

        return $query->exists();
    }

    /**
     * Record that an email of this type was queued for the customer.
     *
     * @param int $accountId
     * @param string $type
     * @param int $customerId
     * @param int|null $refId
     * @return NotificationEmailLog
     */
    public function log(int $accountId, string $type, int $customerId, ?int $refId = null): NotificationEmailLog
    {
        return NotificationEmailLog::create([
            'account_id' => $accountId,
            'customer_id' => $customerId,
            'type' => $type,
            'ref_id' => $refId,
        ]);
    }
}
