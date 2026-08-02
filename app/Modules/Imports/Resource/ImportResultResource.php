<?php

namespace App\Modules\Imports\Resource;

use Illuminate\Http\Resources\Json\JsonResource;

class ImportResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'importJobId' => $this->import_job_id,
            'rowNumber' => $this->row_number,
            'status' => $this->status,
            'originalData' => $this->original_data,
            'errors' => $this->errors,
            'message' => $this->message,
            'createdRecordId' => $this->created_record_id,
            'createdAt' => $this->created_at,
        ];
    }
}
