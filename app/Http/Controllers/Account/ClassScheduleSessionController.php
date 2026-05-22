<?php

namespace App\Http\Controllers\Account;

use App\Helpers\ApiResponse;
use App\Http\Requests\Account\ClassScheduleSessionRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\ClassScheduleSessionResource;
use App\Repositories\Account\ClassScheduleSessionRepository;
use App\Services\Account\ClassScheduleService;
use App\Services\Account\GroupClassSessionAuthorizationService;
use Illuminate\Http\JsonResponse;

class ClassScheduleSessionController
{
    public function __construct(
        private ClassScheduleSessionRepository $classScheduleSessionRepository,
        private ClassScheduleService $classScheduleService,
        private GroupClassSessionAuthorizationService $groupClassSessionAuthorization
    ) {
    }

    /**
     * Get all class schedule sessions
     *
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getAllSessions(GenericRequest $request): JsonResponse
    {
        $data = $request->getGenericData();
        $sessions = $this->classScheduleSessionRepository->getAllSessions($data);
        return ApiResponse::success(ClassScheduleSessionResource::collection($sessions)->response()->getData(true));
    }

    /**
     * Cancel a class schedule session and all its member bookings.
     *
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function cancelSession(GenericRequest $request, int $id): JsonResponse
    {
        try {
            $genericData = $request->getGenericData();
            $accountId = (int) $genericData->userData->account_id;
            $session = $this->classScheduleSessionRepository->getSessionById($id, $accountId);
            $this->groupClassSessionAuthorization->assertMayManageGroupClassSession(
                $genericData->userData,
                $session
            );

            $this->classScheduleSessionRepository->cancelSession($id, $accountId);

            return ApiResponse::success(null, 'Session cancelled successfully');
        } catch (\Exception $e) {
            if (GroupClassSessionAuthorizationService::isForbiddenAuthorizationMessage($e->getMessage())) {
                return ApiResponse::error($e->getMessage(), 403);
            }

            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Update a class schedule session
     *
     * @param ClassScheduleSessionRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateSession(ClassScheduleSessionRequest $request, int $id): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $accountId = (int) $genericData->userData->account_id;
            $session = $this->classScheduleSessionRepository->getSessionById($id, $accountId);
            $this->groupClassSessionAuthorization->assertMayManageGroupClassSession(
                $genericData->userData,
                $session
            );

            $session = $this->classScheduleService->updateClassScheduleSession($id, $genericData);

            return ApiResponse::success(new ClassScheduleSessionResource($session), 'Session updated successfully');
        } catch (\Exception $e) {
            if (GroupClassSessionAuthorizationService::isForbiddenAuthorizationMessage($e->getMessage())) {
                return ApiResponse::error($e->getMessage(), 403);
            }

            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
