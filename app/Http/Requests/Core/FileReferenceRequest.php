<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\GenericRequest;

/**
 * Reference to a file the client has already uploaded straight to R2 via the
 * presigned URL: the object path plus its size in KB, used to update the
 * account's storage counter.
 *
 * Shared by all single-file endpoints (user avatar, customer photo, …) — the
 * backend never receives the file itself, only this reference.
 */
class FileReferenceRequest extends GenericRequest
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
            'path' => ['required', 'string', 'max:500'],
            'sizeKb' => ['required', 'numeric', 'min:0'],
        ]);
    }

    /**
     * R2 object path of the uploaded file.
     *
     * @return string
     */
    public function getPath(): string
    {
        return (string) $this->validated('path');
    }

    /**
     * Client-reported size of the uploaded file, in KB.
     *
     * @return float
     */
    public function getSizeKb(): float
    {
        return (float) $this->validated('sizeKb');
    }
}
