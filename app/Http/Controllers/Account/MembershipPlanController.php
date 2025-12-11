<?php

namespace App\Http\Controllers\Account;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\MembershipPlanRequest;
use App\Http\Resources\Account\MembershipPlanResource;
use App\Repositories\Account\MembershipPlanRepository;
use Illuminate\Http\JsonResponse;

class MembershipPlanController extends Controller
{
    public function __construct(
        private MembershipPlanRepository $repository
    ) {
    }

    /**
     * Get all membership plans by account_id
     *
     * @return JsonResponse
     */
    public function getAllMembershipPlan(): JsonResponse
    {
        $plans = $this->repository->getAll();
        return ApiResponse::success(MembershipPlanResource::collection($plans));
    }

    /**
     * Create a new membership plan
     *
     * @param MembershipPlanRequest $request
     * @return JsonResponse
     */
    public function store(MembershipPlanRequest $request): JsonResponse
    {
        $plan = $this->repository->create($request->validated());
        return ApiResponse::success(new MembershipPlanResource($plan), 'Membership plan created successfully', 201);
    }

    /**
     * Update a membership plan
     *
     * @param MembershipPlanRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateMembershipPlan(MembershipPlanRequest $request, int $id): JsonResponse
    {
        $plan = $this->repository->update($id, $request->validated());
        return ApiResponse::success(new MembershipPlanResource($plan), 'Membership plan updated successfully');
    }

    /**
     * Delete a membership plan (soft delete)
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $this->repository->delete($id);
        return ApiResponse::success(null, 'Membership plan deleted successfully');
    }
}

