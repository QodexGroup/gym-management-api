<?php

namespace App\Http\Controllers\Account;

use App\Helpers\ApiResponse;
use App\Http\Requests\Account\UserRequest;
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
     * @return JsonResponse
     */
    public function getAllUsers(): JsonResponse
    {
        $users = $this->usersRepository->getAllUsers();
        return ApiResponse::success(UserResource::collection($users));
    }

    /**
     * @param UserRequest $request
     *
     * @return JsonResponse
     */
    public function createUser(UserRequest $request): JsonResponse
    {
        $user = $this->usersService->createUser($request->validated());
        return ApiResponse::success(new UserResource($user), 'User created successfully');
    }

    /**
     * @param UserRequest $request
     * @param mixed $id
     *
     * @return JsonResponse
     */
    public function updateUser(UserRequest $request, $id): JsonResponse
    {
        $user = $this->usersService->updateUser($id, $request->validated());
        return ApiResponse::success(new UserResource($user), 'User updated successfully');
    }

    /**
     * @param int $id
     *
     * @return JsonResponse
     */
    public function deleteUser(int $id): JsonResponse
    {
        $this->usersRepository->deleteUser($id);
        return ApiResponse::success(null, 'User deleted successfully');
    }

    /**
     * @param int $id
     *
     * @return JsonResponse
     */
    public function deactivateUser(int $id): JsonResponse
    {
        $this->usersRepository->deactivateUser($id);
        return ApiResponse::success(null, 'User deactivated successfully');
    }
}
