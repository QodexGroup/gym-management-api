<?php

namespace App\Http\Requests\Common;

use App\Http\Requests\GenericRequest;

class ExpenseRequest extends GenericRequest
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
            'categoryId' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expenseDate' => ['required', 'date'],
            'status' => ['required', 'string', 'in:POSTED,UNPOSTED'],
        ]);
    }
}

