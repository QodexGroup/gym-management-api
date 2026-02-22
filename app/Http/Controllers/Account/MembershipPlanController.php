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
    public function store(MembershipPlanRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $check = $this->accountLimitService->canCreate($genericData->userData->account_id, AccountLimitService::RESOURCE_MEMBERSHIP_PLANS);
            if (!$check['allowed']) {
                return ApiResponse::error($check['message'] ?? 'Limit reached', 403);
            }
            $plan = $this->membershipPlanRepository->createMembershipPlan($genericData);
            return ApiResponse::success(new MembershipPlanResource($plan), 'Membership plan created successfully', 201);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'limit') || str_contains($e->getMessage(), 'trial')) {
                return ApiResponse::error($e->getMessage(), 403);
            }
            throw $e;
        }
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
    public function delete(GenericRequest $request, int $id): JsonResponse
    {
        $data = $request->getGenericData();
        $this->membershipPlanRepository->deleteMembershipPlan($id, $data->userData->account_id);
        return ApiResponse::success(null, 'Membership plan deleted successfully');
    }
}

