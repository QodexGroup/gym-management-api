<?php

namespace App\Http\Controllers\Account;

use App\Helpers\ApiResponse;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\ClassScheduleSessionResource;
use App\Repositories\Account\ClassScheduleSessionRepository;
use Illuminate\Http\JsonResponse;

class ClassScheduleSessionController
{
    public function __construct(
        private ClassScheduleSessionRepository $classScheduleSessionRepository
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
}
