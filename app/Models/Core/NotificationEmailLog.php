<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

/**
 * Log of customer-facing notification emails, used to dedupe the email lane
 * independently of in-app notification records.
 *
 * The status reflects what actually happened to the email, not merely that a
 * job was queued: only STATUS_QUEUED and STATUS_SENT suppress a resend, so a
 * send the mail provider rejected is retried on the next run.
 */
class NotificationEmailLog extends Model
{
    /** Queued for delivery; the worker has not reported back yet. */
    public const STATUS_QUEUED = 'queued';

    /** Handed to the mail provider successfully. */
    public const STATUS_SENT = 'sent';

    /** Given up on. Does not suppress a later attempt. */
    public const STATUS_FAILED = 'failed';

    /**
     * Statuses that count as "already handled" for dedupe purposes.
     *
     * @var array<int, string>
     */
    public const BLOCKING_STATUSES = [self::STATUS_QUEUED, self::STATUS_SENT];

    protected $table = 'tb_notification_email_logs';

    protected $fillable = [
        'account_id',
        'customer_id',
        'type',
        'ref_id',
        'status',
        'sent_at',
        'failed_reason',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the customer this email was sent to.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
