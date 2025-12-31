<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Core\NotificationResource;
use App\Services\Core\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {
    }

    /**
     * Get all notifications (paginated).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 20);
        $unreadOnly = $request->query('unread_only', false);

        $result = $this->notificationService->getNotifications((int) $page, (int) $limit);

        // Filter unread only if requested
        if ($unreadOnly) {
            $result['data'] = $result['data']->filter(function ($notification) {
                return $notification->isUnread();
            })->values();
            $result['pagination']['total'] = $result['data']->count();
        }

        return ApiResponse::success([
            'notifications' => NotificationResource::collection($result['data']),
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * Get unread notification count.
     *
     * @return JsonResponse
     */
    public function getUnreadCount(): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount();

        return ApiResponse::success(['count' => $count]);
    }

    /**
     * Mark a notification as read.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function markAsRead(int $id): JsonResponse
    {
        $notification = $this->notificationService->markAsRead($id);

        return ApiResponse::success(
            new NotificationResource($notification),
            'Notification marked as read'
        );
    }

    /**
     * Mark all notifications as read.
     *
     * @return JsonResponse
     */
    public function markAllAsRead(): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead();

        return ApiResponse::success(
            ['marked_count' => $count],
            "{$count} notifications marked as read"
        );
    }
}
