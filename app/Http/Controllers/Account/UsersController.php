<?php

namespace App\Http\Controllers\Account;

use App\Helpers\ApiResponse;
use App\Http\Requests\Account\UserRequest;
use App\Http\Requests\Account\ResetPasswordRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\UserResource;
use App\Repositories\Account\UsersRepository;
use App\Services\Account\UsersService;
use Illuminate\Http\JsonResponse;

class UsersController
{

    public function __construct(
        private UsersRepository $usersRepository,
        private UsersService $usersService
    )
    {
    }

    /**
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getAllUsers(GenericRequest $request): JsonResponse
    {
        $data = $request->getGenericData();
        $users = $this->usersRepository->getAllUsers($data);
        return ApiResponse::success($users);
    }

    /**
     * @param UserRequest $request
     *
     * @return JsonResponse
     */
    public function createUser(UserRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $user = $this->usersService->createUser($genericData);
            return ApiResponse::success(new UserResource($user), 'User created successfully');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'limit') || str_contains($e->getMessage(), 'trial')) {
                return ApiResponse::error($e->getMessage(), 403);
            }
            throw $e;
        }
    }

    /**
     * @param UserRequest $request
     * @param mixed $id
     *
     * @return JsonResponse
     */
    public function updateUser(UserRequest $request, $id): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $user = $this->usersService->updateUser($id, $genericData);
        return ApiResponse::success(new UserResource($user), 'User updated successfully');
    }

    /**
     * @param GenericRequest $request
     * @param int $id
     *
     * @return JsonResponse
     */
    public function deleteUser(GenericRequest $request, int $id): JsonResponse
    {
        $data = $request->getGenericData();
        $this->usersService->deleteUser($id, $data->userData->account_id);
        return ApiResponse::success(null, 'User deleted successfully');
    }

    /**
     * @param GenericRequest $request
     * @param int $id
     *
     * @return JsonResponse
     */
    public function deactivateUser(GenericRequest $request, int $id): JsonResponse
    {
        $data = $request->getGenericData();
        $this->usersRepository->deactivateUser($id, $data->userData->account_id);
        return ApiResponse::success(null, 'User deactivated successfully');
    }

    /**
     * @param GenericRequest $request
     * @param int $id
     *
     * @return JsonResponse
     */
    public function activateUser(GenericRequest $request, int $id): JsonResponse
    {
        $data = $request->getGenericData();
        $this->usersRepository->activateUser($id, $data->userData->account_id);
        return ApiResponse::success(null, 'User activated successfully');
    }

    /**
     * @param ResetPasswordRequest $request
     * @param int $id
     *
     * @return JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request, int $id): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $user = $this->usersService->resetPassword($id, $genericData);
        return ApiResponse::success(new UserResource($user), 'Password reset successfully');
    }

    /**
     * Get all coaches (users with role 'coach' and status 'active')
     *
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getCoaches(GenericRequest $request): JsonResponse
    {
        $data = $request->getGenericData();
        $coaches = $this->usersRepository->getAllCoaches($data);
        return ApiResponse::success(UserResource::collection($coaches)->response()->getData(true)['data'] ?? []);
    }
}
