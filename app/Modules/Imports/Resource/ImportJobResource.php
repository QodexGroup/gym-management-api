<?php

namespace App\Modules\Imports\Resource;

use Illuminate\Http\Resources\Json\JsonResource;

class ImportJobResource extends JsonResource
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
            'accountId' => $this->account_id,
            'importType' => $this->import_type,
            'originalFilename' => $this->original_filename,
            'status' => $this->status,
            'totalRows' => $this->total_rows,
            'processedRows' => $this->processed_rows,
            'successCount' => $this->success_count,
            'failureCount' => $this->failure_count,
            'skippedCount' => $this->skipped_count,
            'progressPercentage' => $this->progress_percentage,
            'columnMapping' => $this->column_mapping,
            'fileHeaders' => $this->file_headers,
            'options' => $this->options,
            'errorMessage' => $this->error_message,
            'hasResultFile' => !empty($this->result_file_path),
            'startedAt' => $this->started_at,
            'completedAt' => $this->completed_at,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
