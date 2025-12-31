<?php

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tb_notifications';

    protected $fillable = [
        'account_id',
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the notification.
     * Uncomment when user management is implemented.
     */
    // public function user()
    // {
    //     return $this->belongsTo(User::class, 'user_id');
    // }

    /**
     * Get the customer related to this notification.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Scope to filter unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope to filter notifications for a specific user.
     * Uncomment when user management is implemented.
     */
    // public function scopeForUser($query, $userId)
    // {
    //     return $query->where('user_id', $userId);
    // }

    /**
     * Scope to filter global notifications (no specific user).
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope to filter by notification type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Mark this notification as read.
     */
    public function markAsRead()
    {
        $this->read_at = now();
        $this->save();
        return $this;
    }

    /**
     * Check if notification is read.
     */
    public function isRead()
    {
        return !is_null($this->read_at);
    }

    /**
     * Check if notification is unread.
     */
    public function isUnread()
    {
        return is_null($this->read_at);
    }
}
