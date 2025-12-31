<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\GenericRequest;
use Illuminate\Validation\Rule;

class UserRequest extends GenericRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('id'); // For update requests
        $userData = $this->getUserData(); // Get user data from GenericRequest
        $accountId = $userData?->account_id; // Get account_id from authenticated user

        return array_merge(parent::rules(), [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'email' => [
                $this->isMethod('post') ? 'required' : 'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where('account_id', $accountId)
                    ->whereNull('deleted_at')
                    ->ignore($userId),
            ],
            'password' => [
                $this->isMethod('post') ? 'required' : 'nullable',
                'string',
                'min:6',
            ],
            'role' => ['required', 'string', 'in:admin,staff,coach'],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'in:active,deactivated'],
            'firebaseUid' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'firstname.required' => 'First name is required.',
            'lastname.required' => 'Last name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already in use.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 6 characters.',
            'role.required' => 'Role is required.',
            'role.in' => 'Role must be one of: admin, staff, or coach.',
            'status.in' => 'Status must be either active or deactivated.',
        ];
    }
}

