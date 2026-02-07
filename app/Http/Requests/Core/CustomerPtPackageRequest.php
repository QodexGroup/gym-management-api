<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\GenericRequest;

class CustomerPtPackageRequest extends GenericRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'ptPackageId' => [
                'required',
                'integer',
                'exists:tb_pt_packages,id',
            ],
            'startDate' => [
                'nullable',
                'date',
            ],
            'coachId' => [
                'nullable',
                'integer',
            ],
        ]);
    }
}
