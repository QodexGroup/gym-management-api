<?php

namespace App\Repositories\Core;

use App\Constant\NotificationEmailLogConstant;
use App\Models\Core\NotificationEmailLog;
use App\Repositories\BaseRepository;

class NotificationEmailLogRepository extends BaseRepository
{
    /**
     * Check whether an email of this type is already queued or sent for the
     * customer (and optional related record) within the last N hours.
     *
     * Rows marked failed are ignored on purpose: the customer never received
     * that email, so the caller must be allowed to try again.
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
            ->whereIn('status', NotificationEmailLogConstant::BLOCKING_STATUSES)
            ->where('created_at', '>=', now()->subHours($hoursThreshold));

        if ($refId !== null) {
            $query->where('ref_id', $refId);
        }

        return $query->exists();
    }

    /**
     * Reserve the email for this customer before the job is dispatched, so a
     * concurrent scheduler run is deduped while the job is still in flight.
     * The row starts as queued and the job settles it either way.
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
            'status' => NotificationEmailLogConstant::STATUS_QUEUED,
        ]);
    }

    /**
     * Record that the mail provider accepted the email.
     *
     * @param int $id
     * @return void
     */
    public function markSent(int $id): void
    {
        NotificationEmailLog::where('id', $id)->update([
            'status' => NotificationEmailLogConstant::STATUS_SENT,
            'sent_at' => now(),
            'failed_reason' => null,
        ]);
    }

    /**
     * Record that the email was given up on, which frees the customer to be
     * picked up again by the next run.
     *
     * @param int $id
     * @param string|null $reason
     * @return void
     */
    public function markFailed(int $id, ?string $reason = null): void
    {
        NotificationEmailLog::where('id', $id)->update([
            'status' => NotificationEmailLogConstant::STATUS_FAILED,
            'failed_reason' => $reason !== null ? mb_substr($reason, 0, 1000) : null,
        ]);
    }

    /**
     * Whether this row has already been settled as delivered. Used by the job
     * to avoid resending when a killed worker causes it to run twice.
     *
     * @param int $id
     * @return bool
     */
    public function isSent(int $id): bool
    {
        return NotificationEmailLog::where('id', $id)
            ->where('status', NotificationEmailLogConstant::STATUS_SENT)
            ->exists();
    }
}
