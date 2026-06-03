<?php

namespace App\Http\Requests\Auth;

use App\Rules\ValidEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignUpRequest extends FormRequest
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
        return [
            'accountName' => ['required', 'string', 'max:255'],
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                new ValidEmail(),
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'billingName' => ['required', 'string', 'max:255'],
            'billingEmail' => ['required', 'max:255', new ValidEmail()],
            'billingPhone' => ['required', 'string', 'max:50'],
            'billingAddress' => ['required', 'string', 'max:255'],
            'billingCity' => ['required', 'string', 'max:100'],
            'billingProvince' => ['required', 'string', 'max:100'],
            'billingZip' => ['required', 'string', 'max:20'],
            'billingCountry' => ['required', 'string', 'size:2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already in use.',
        ];
    }
}
