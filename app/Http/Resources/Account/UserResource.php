<?php

namespace App\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $adminEmails = config('app.platform_admin_emails', []);
        $isPlatformAdmin = !empty($adminEmails) && in_array($this->email, $adminEmails, true);
        $emailVerified = $request->attributes->get('email_verified', false);

        return [
            'id' => $this->id,
            'accountId' => $this->account_id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'fullname' => $this->full_name,
            'email' => $this->email,
            'role' => $this->role,
            'phone' => $this->phone,
            'status' => $this->status,
            'firebaseUid' => $this->firebase_uid,
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->pluck('permission')->toArray();
            }, []),
            'isPlatformAdmin' => $isPlatformAdmin,
            'emailVerified' => $emailVerified,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'deletedAt' => $this->deleted_at,
        ];
    }
}

