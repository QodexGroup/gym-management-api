<?php

namespace App\Repositories\Account;

use App\Constant\UserStatusConstant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class UsersRepository
{
    /**
     * @return Collection
     */
    public function getAllUsers(): Collection
    {
        $accountId = Auth::user()->account_id;
        return User::where('account_id', $accountId)
            ->with('permissions')
            ->get();
    }

    /**
     * @param array $data
     *
     * @return User
     */
    public function createUser(array $data): User
    {
        $accountId = Auth::user()->account_id;
        $data['account_id'] = $accountId;
        return User::create($data)->fresh();
    }

    /**
     * @param int $id
     * @param array $data
     *
     * @return User
     */
    public function updateUser(int $id, array $data): User
    {
        $user = User::where('id', $id)->where('account_id', Auth::user()->account_id)->firstOrFail();
        $user->update($data);
        return $user->fresh();
    }

    /**
     * @param int $id
     *
     * @return bool
     */
    public function deactivateUser(int $id): bool
    {
        return User::where('id', $id)
        ->where('account_id', Auth::user()->account_id)
        ->update(['status' => UserStatusConstant::DEACTIVATED]);
    }

    /**
     * @param int $id
     *
     * @return bool
     */
    public function deleteUser(int $id): bool
    {
        return User::where('id', $id)->where('account_id', Auth::user()->account_id)->delete();
    }
}
