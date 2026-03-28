<?php

namespace App\Http\Controllers\Common;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Common\NotificationResource;
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
        $accountId = $this->getAccountId($request);
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 20);
        $unreadOnly = filter_var($request->query('unread_only', false), FILTER_VALIDATE_BOOLEAN);

        $result = $this->notificationService->getNotifications($accountId, (int) $page, (int) $limit);

        if ($unreadOnly) {
            $result['data'] = $result['data']->filter(function ($notification) {
                return $notification->isUnread();
            })->values();
            $result['pagination']['total'] = $result['data']->count();
            $result['pagination']['last_page'] = (int) max(1, ceil($result['pagination']['total'] / $limit));
        }

        $notifications = NotificationResource::collection($result['data'])->resolve();

        return ApiResponse::success([
            'notifications' => $notifications,
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * Get unread notification count.
     *
     * @return JsonResponse
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount($this->getAccountId($request));

        return ApiResponse::success(['count' => $count]);
    }

    /**
     * Mark a notification as read.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = $this->notificationService->markAsRead($id, $this->getAccountId($request));

        return ApiResponse::success(
            new NotificationResource($notification),
            'Notification marked as read'
        );
    }

    /**
     * Mark all notifications as read.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($this->getAccountId($request));

        return ApiResponse::success(
            ['marked_count' => $count],
            "{$count} notifications marked as read"
        );
    }
    /**
     * Get the authenticated user's account ID from the request.
     *
     * @param Request $request
     * @return int
     */
    private function getAccountId(Request $request): int
    {
        $user = $request->attributes->get('user');

        if (!$user || !$user->account_id) {
            abort(401, 'Unauthorized');
        }

        return (int) $user->account_id;
    }
}
