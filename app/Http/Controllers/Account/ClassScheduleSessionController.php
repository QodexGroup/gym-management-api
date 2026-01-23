<?php

namespace App\Http\Controllers\Account;

use App\Helpers\ApiResponse;
use App\Http\Requests\Account\ClassScheduleSessionRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\ClassScheduleSessionResource;
use App\Repositories\Account\ClassScheduleSessionRepository;
use App\Services\Account\ClassScheduleService;
use Illuminate\Http\JsonResponse;

class ClassScheduleSessionController
{
    public function __construct(
        private ClassScheduleSessionRepository $classScheduleSessionRepository,
        private ClassScheduleService $classScheduleService
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
        return ApiResponse::success(ClassScheduleSessionResource::collection($sessions));
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
            $session = $this->classScheduleService->updateClassScheduleSession($id, $genericData);
            return ApiResponse::success(new ClassScheduleSessionResource($session), 'Session updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
