<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

/**
 * Log of customer-facing notification emails, used to dedupe the email lane
 * independently of in-app notification records.
 *
 * The status reflects what actually happened to the email, not merely that a
 * job was queued: only the statuses in
 * NotificationEmailLogConstant::BLOCKING_STATUSES suppress a resend, so a send
 * the mail provider rejected is retried on the next run.
 */
class NotificationEmailLog extends Model
{
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
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
