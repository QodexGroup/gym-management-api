<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\GenericRequest;

class AccountRequest extends GenericRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'accountName' => ['required', 'string', 'max:255'],
            'accountEmail' => ['required', 'email', 'max:255'],
            'accountPhone' => ['required', 'string', 'max:50'],
            'billingName' => ['required', 'string', 'max:255'],
            'billingEmail' => ['required', 'email', 'max:255'],
            'billingPhone' => ['required', 'string', 'max:50'],
            'billingAddress' => ['required', 'string', 'max:255'],
            'billingCity' => ['required', 'string', 'max:100'],
            'billingProvince' => ['required', 'string', 'max:100'],
            'billingZip' => ['required', 'string', 'max:20'],
            'billingCountry' => ['required', 'string', 'size:2'],
        ];
    }
}
