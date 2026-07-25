<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $table = 'tb_notification_preferences';

    protected $fillable = [
        'account_id',
        'membership_expiry_enabled',
        'payment_alerts_enabled',
        'new_registrations_enabled',
    ];

    protected $casts = [
        'membership_expiry_enabled' => 'boolean',
        'payment_alerts_enabled' => 'boolean',
        'new_registrations_enabled' => 'boolean',
    ];
}
