<?php

namespace App\Http\Controllers\Account;

use App\Helpers\ApiResponse;
use App\Http\Requests\Account\MembershipPlanRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\MembershipPlanResource;
use App\Repositories\Account\MembershipPlanRepository;
use App\Services\Account\AccountLimitService;
use Illuminate\Http\JsonResponse;

class MembershipPlanController
{
    public function __construct(
        private MembershipPlanRepository $membershipPlanRepository,
        private AccountLimitService $accountLimitService
    ) {
    }

    /**
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getAllMembershipPlan(GenericRequest $request): JsonResponse
    {
        $data = $request->getGenericData();
        $plans = $this->membershipPlanRepository->getAllMembershipPlans($data);
        return ApiResponse::success(MembershipPlanResource::collection($plans)->response()->getData(true));
    }

    /**
     * @param MembershipPlanRequest $request
     * @return JsonResponse
     */
    public function createMembershipPlan(MembershipPlanRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $check = $this->accountLimitService->canCreate($genericData->userData->account_id, AccountLimitService::RESOURCE_MEMBERSHIP_PLANS);
        if (!$check['allowed']) {
            return ApiResponse::error($check['message'] ?? 'Not allowed', 403);
        }
        $plan = $this->membershipPlanRepository->createMembershipPlan($genericData);
        return ApiResponse::success(new MembershipPlanResource($plan), 'Membership plan created successfully', 201);
    }

    /**
     * @param MembershipPlanRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateMembershipPlan(MembershipPlanRequest $request, int $id): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $plan = $this->membershipPlanRepository->updateMembershipPlan($id, $genericData);
        return ApiResponse::success(new MembershipPlanResource($plan), 'Membership plan updated successfully');
    }

    /**
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function deleteMembershipPlan(GenericRequest $request, int $id): JsonResponse
    {
        $data = $request->getGenericData();
        $this->membershipPlanRepository->deleteMembershipPlan($id, $data->userData->account_id);
        return ApiResponse::success(null, 'Membership plan deleted successfully');
    }
}

