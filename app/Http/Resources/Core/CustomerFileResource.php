<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerFileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accountId' => $this->account_id,
            'customerId' => $this->customer_id,
            'fileableType' => $this->fileable_type,
            'fileableId' => $this->fileable_id,
            'remarks' => $this->remarks,
            'fileName' => $this->file_name,
            'fileUrl' => $this->file_url,
            'fileSize' => $this->file_size ? (float)$this->file_size : null,
            'mimeType' => $this->mime_type,
            'fileDate' => $this->file_date,
            'uploadedBy' => $this->uploaded_by,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'deletedAt' => $this->deleted_at,
        ];
    }
}

