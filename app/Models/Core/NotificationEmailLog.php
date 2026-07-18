<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

/**
 * Log of customer-facing notification emails that were queued, used to dedupe
 * the email lane independently of in-app notification records.
 */
class NotificationEmailLog extends Model
{
    protected $table = 'tb_notification_email_logs';

    protected $fillable = [
        'account_id',
        'customer_id',
        'type',
        'ref_id',
    ];

    protected $casts = [
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
