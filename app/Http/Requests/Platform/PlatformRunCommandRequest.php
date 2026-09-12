<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of a command run. Authorisation is the service token middleware on the
 * route; what remains is shape.
 *
 * `params` is only shape-checked here — WHICH parameters are acceptable is the
 * registry's business, and PlatformCommandService drops anything undeclared.
 */
class PlatformRunCommandRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'accountId' => ['nullable', 'integer', 'min:1'],
            'params' => ['sometimes', 'array'],
        ];
    }

    /**
     * The account this run is scoped to, or null for a global command.
     *
     * @return int|null
     */
    public function accountId(): ?int
    {
        $accountId = $this->input('accountId');

        return $accountId === null || $accountId === '' ? null : (int) $accountId;
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return (array) $this->input('params', []);
    }
}
