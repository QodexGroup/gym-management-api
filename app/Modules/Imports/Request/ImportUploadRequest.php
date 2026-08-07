<?php

namespace App\Modules\Imports\Request;

use App\Modules\Imports\Constants\ImportConstant;
use App\Http\Requests\GenericRequest;

class ImportUploadRequest extends GenericRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'importType' => ['required', 'string', 'max:50'],
            'file' => [
                'required',
                'file',
                'max:' . ImportConstant::MAX_FILE_SIZE_KB,
                'extensions:' . implode(',', ImportConstant::SUPPORTED_EXTENSIONS),
            ],
        ];
    }
}
