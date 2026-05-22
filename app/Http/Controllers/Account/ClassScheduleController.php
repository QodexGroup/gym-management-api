<?php

namespace App\Http\Controllers\Account;

use App\Constant\ClassTypeScheduleConstant;
use App\Helpers\ApiResponse;
use App\Http\Requests\Account\ClassScheduleRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\ClassScheduleResource;
use App\Repositories\Account\ClassScheduleRepository;
use App\Services\Account\ClassScheduleService;
use App\Services\Account\GroupClassSessionAuthorizationService;
use Illuminate\Http\JsonResponse;

class ClassScheduleController
{
    public function __construct(
        private ClassScheduleRepository $classScheduleRepository,
        private ClassScheduleService $classScheduleService,
        private GroupClassSessionAuthorizationService $groupClassSessionAuthorization
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
        return ApiResponse::success(ClassScheduleResource::collection($schedules)->response()->getData(true));
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
        return ApiResponse::success(ClassScheduleResource::collection($schedules)->response()->getData(true));
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
        return ApiResponse::success(ClassScheduleResource::collection($schedules)->response()->getData(true));
    }

    /**
     * @param ClassScheduleRequest $request
     * @return JsonResponse
     */
    public function store(ClassScheduleRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $dto = $genericData->getData();
            $this->groupClassSessionAuthorization->assertCoachSelfAssignCoachIdOnCreate(
                $genericData->userData,
                (int) $dto->coachId
            );
            $dto->classType = ClassTypeScheduleConstant::GROUP_CLASS;
            $schedule = $this->classScheduleService->createClassSchedule($genericData);
            return ApiResponse::success(new ClassScheduleResource($schedule), 'Class schedule created successfully', 201);
        } catch (\Exception $e) {
            if (GroupClassSessionAuthorizationService::isForbiddenAuthorizationMessage($e->getMessage())) {
                return ApiResponse::error($e->getMessage(), 403);
            }
            if (str_contains($e->getMessage(), 'limit') || str_contains($e->getMessage(), 'trial')) {
                return ApiResponse::error($e->getMessage(), 403);
            }
            throw $e;
        }
    }

    /**
     * @param ClassScheduleRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateClassSchedule(ClassScheduleRequest $request, int $id): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $accountId = (int) $genericData->userData->account_id;
            $schedule = $this->classScheduleRepository->findClassScheduleById($id, $accountId);
            $this->groupClassSessionAuthorization->assertMayManageExistingGroupSchedule(
                $genericData->userData,
                $schedule
            );

            $dto = $genericData->getData();
            $coachFromPayload = isset($dto->coachId) ? (int) $dto->coachId : null;
            $this->groupClassSessionAuthorization->assertCoachGroupScheduleUpdateCoachIdAllowed(
                $genericData->userData,
                $coachFromPayload
            );

            $schedule = $this->classScheduleService->updateClassSchedule($id, $genericData);

            return ApiResponse::success(new ClassScheduleResource($schedule), 'Class schedule updated successfully');
        } catch (\Exception $e) {
            if (GroupClassSessionAuthorizationService::isForbiddenAuthorizationMessage($e->getMessage())) {
                return ApiResponse::error($e->getMessage(), 403);
            }

            throw $e;
        }
    }

    /**
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function delete(GenericRequest $request, int $id): JsonResponse
    {
        try {
            $genericData = $request->getGenericData();
            $accountId = (int) $genericData->userData->account_id;
            $schedule = $this->classScheduleRepository->findClassScheduleById($id, $accountId);
            $this->groupClassSessionAuthorization->assertMayManageExistingGroupSchedule(
                $genericData->userData,
                $schedule
            );
            $this->classScheduleService->deleteClassSchedule($id, $genericData);

            return ApiResponse::success(null, 'Class schedule deleted successfully');
        } catch (\Exception $e) {
            if (GroupClassSessionAuthorizationService::isForbiddenAuthorizationMessage($e->getMessage())) {
                return ApiResponse::error($e->getMessage(), 403);
            }

            throw $e;
        }
    }
}
