<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\GenericRequest;

class ApproveSubscriptionRequestRequest extends GenericRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'rejectionReason' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
