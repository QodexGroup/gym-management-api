<?php

namespace App\Constant;

class NotificationEmailLogConstant
{
    // Delivery Statuses
    /** Queued for delivery; the worker has not reported back yet. */
    const STATUS_QUEUED = 'queued';

    /** Handed to the mail provider successfully. */
    const STATUS_SENT = 'sent';

    /** Given up on. Does not suppress a later attempt. */
    const STATUS_FAILED = 'failed';

    /**
     * Statuses that count as "already handled" for dedupe purposes.
     *
     * A failed row is deliberately absent: the customer never received that
     * email, so the next run must be free to try again.
     *
     * @var array<int, string>
     */
    const BLOCKING_STATUSES = [self::STATUS_QUEUED, self::STATUS_SENT];
}
