<?php

namespace App\Http\Controllers\Account;

use App\Helpers\ApiResponse;
use App\Http\Requests\Account\MembershipPlanRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\MembershipPlanResource;
use App\Repositories\Account\MembershipPlanRepository;
use Illuminate\Http\JsonResponse;

class MembershipPlanController
{
    public function __construct(
        private MembershipPlanRepository $membershipPlanRepository
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
        $genericData = $request->getGenericDataWithValidated();
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
    public function delete(GenericRequest $request, int $id): JsonResponse
    {
        $data = $request->getGenericData();
        $this->membershipPlanRepository->deleteMembershipPlan($id, $data->userData->account_id);
        return ApiResponse::success(null, 'Membership plan deleted successfully');
    }
}

