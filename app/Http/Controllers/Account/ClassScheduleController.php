<?php

namespace App\Http\Controllers\Account;

use App\Constant\ClassTypeScheduleConstant;
use App\Helpers\ApiResponse;
use App\Http\Requests\Account\ClassScheduleRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\ClassScheduleResource;
use App\Repositories\Account\ClassScheduleRepository;
use App\Services\Account\ClassScheduleService;
use Illuminate\Http\JsonResponse;

class ClassScheduleController
{
    public function __construct(
        private ClassScheduleRepository $classScheduleRepository,
        private ClassScheduleService $classScheduleService
    ) {
    }

    /**
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getAllClassSchedules(GenericRequest $request): JsonResponse
    {
        $data = $request->getGenericData();
        $schedules = $this->classScheduleRepository->getAllClassSchedules($data);
        return ApiResponse::success($schedules);
    }

    /**
     * Get class schedules for the current logged-in coach
     *
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getMyClassSchedules(GenericRequest $request): JsonResponse
    {
        $data = $request->getGenericData();
        $coachId = $data->userData->id;
        $schedules = $this->classScheduleRepository->getClassSchedulesByCoachId($data, $coachId);
        return ApiResponse::success($schedules);
    }

    /**
     * Get class schedules by coach ID
     *
     * @param GenericRequest $request
     * @param int $coachId
     * @return JsonResponse
     */
    public function getClassSchedulesByCoachId(GenericRequest $request, int $coachId): JsonResponse
    {
        $data = $request->getGenericData();
        $schedules = $this->classScheduleRepository->getClassSchedulesByCoachId($data, $coachId);
        return ApiResponse::success($schedules);
    }

    /**
     * @param ClassScheduleRequest $request
     * @return JsonResponse
     */
    public function store(ClassScheduleRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $genericData->getData()->classType = ClassTypeScheduleConstant::GROUP_CLASS;
        $schedule = $this->classScheduleService->createClassSchedule($genericData);
        return ApiResponse::success(new ClassScheduleResource($schedule), 'Class schedule created successfully', 201);
    }

    /**
     * @param ClassScheduleRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateClassSchedule(ClassScheduleRequest $request, int $id): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $schedule = $this->classScheduleService->updateClassSchedule($id, $genericData);
        return ApiResponse::success(new ClassScheduleResource($schedule), 'Class schedule updated successfully');
    }

    /**
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function delete(GenericRequest $request, int $id): JsonResponse
    {
        $genericData = $request->getGenericData();
        $this->classScheduleService->deleteClassSchedule($id, $genericData);
        return ApiResponse::success(null, 'Class schedule deleted successfully');
    }
}
