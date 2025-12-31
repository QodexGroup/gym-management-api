<?php

namespace App\Repositories\Core;

use App\Models\Core\Notification;
use Illuminate\Database\Eloquent\Collection;

class NotificationRepository
{
    /**
     * Create a new notification.
     *
     * @param array $data
     * @return Notification
     */
    public function create(array $data): Notification
    {
        // Set defaults
        $data['account_id'] = $data['account_id'] ?? 1;
        
        // For global notifications, user_id should be null
        // Uncomment when user management is implemented
        // $data['user_id'] = $data['user_id'] ?? null;
        
        return Notification::create($data);
    }

    /**
     * Get a notification by ID.
     *
     * @param int $id
     * @return Notification
     */
    public function getById(int $id): Notification
    {
        return Notification::where('account_id', 1)
            ->findOrFail($id);
    }

    /**
     * Get global notifications (paginated).
     * When user management is implemented, uncomment the user-specific version.
     *
     * @param int $limit
     * @return Collection
     */
    public function getGlobalNotifications(int $limit = 50): Collection
    {
        return Notification::where('account_id', 1)
            ->global() // Only global notifications (user_id IS NULL)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get notifications for a specific user (for future use).
     * Uncomment when user management is implemented.
     */
    // public function getByUserId(int $userId, int $limit = 50): Collection
    // {
    //     return Notification::where('account_id', 1)
    //         ->where(function($query) use ($userId) {
    //             $query->where('user_id', $userId)
    //                   ->orWhereNull('user_id'); // Include global notifications
    //         })
    //         ->orderBy('created_at', 'desc')
    //         ->limit($limit)
    //         ->get();
    // }

    /**
     * Get unread global notifications.
     *
     * @return Collection
     */
    public function getUnreadGlobal(): Collection
    {
        return Notification::where('account_id', 1)
            ->global()
            ->unread()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get unread notifications for a specific user (for future use).
     * Uncomment when user management is implemented.
     */
    // public function getUnreadByUserId(int $userId): Collection
    // {
    //     return Notification::where('account_id', 1)
    //         ->where(function($query) use ($userId) {
    //             $query->where('user_id', $userId)
    //                   ->orWhereNull('user_id'); // Include global notifications
    //         })
    //         ->unread()
    //         ->orderBy('created_at', 'desc')
    //         ->get();
    // }

    /**
     * Get unread count for global notifications.
     *
     * @return int
     */
    public function getUnreadCountGlobal(): int
    {
        return Notification::where('account_id', 1)
            ->global()
            ->unread()
            ->count();
    }

    /**
     * Get unread count for a specific user (for future use).
     * Uncomment when user management is implemented.
     */
    // public function getUnreadCountByUserId(int $userId): int
    // {
    //     return Notification::where('account_id', 1)
    //         ->where(function($query) use ($userId) {
    //             $query->where('user_id', $userId)
    //                   ->orWhereNull('user_id'); // Include global notifications
    //         })
    //         ->unread()
    //         ->count();
    // }

    /**
     * Mark a notification as read.
     *
     * @param int $id
     * @return Notification
     */
    public function markAsRead(int $id): Notification
    {
        $notification = $this->getById($id);
        $notification->markAsRead();
        return $notification;
    }

    /**
     * Mark all global notifications as read.
     *
     * @return int Number of notifications marked as read
     */
    public function markAllAsReadGlobal(): int
    {
        return Notification::where('account_id', 1)
            ->global()
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Mark all notifications as read for a specific user (for future use).
     * Uncomment when user management is implemented.
     */
    // public function markAllAsRead(int $userId): int
    // {
    //     return Notification::where('account_id', 1)
    //         ->where(function($query) use ($userId) {
    //             $query->where('user_id', $userId)
    //                   ->orWhereNull('user_id'); // Include global notifications
    //         })
    //         ->unread()
    //         ->update(['read_at' => now()]);
    // }

    /**
     * Check if a notification already exists (to prevent duplicates).
     *
     * @param string $type
     * @param array $data
     * @param int $hoursThreshold
     * @return bool
     */
    public function notificationExists(string $type, array $data, int $hoursThreshold = 24): bool
    {
        $query = Notification::where('account_id', 1)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subHours($hoursThreshold));

        // Check for specific data matches
        if (isset($data['customer_id'])) {
            $query->whereJsonContains('data->customer_id', $data['customer_id']);
        }
        if (isset($data['membership_id'])) {
            $query->whereJsonContains('data->membership_id', $data['membership_id']);
        }

        return $query->exists();
    }
}
