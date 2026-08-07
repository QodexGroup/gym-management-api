<?php

namespace App\Modules\Imports\Services;

use App\Modules\Imports\Constants\ImportConstant;
use App\Modules\Imports\Data\ImportUploadResult;
use App\Helpers\GenericData;
use App\Modules\Imports\Jobs\ProcessImportJob;
use App\Modules\Imports\Models\ImportJob;
use App\Modules\Imports\Repositories\ImportJobRepository;
use App\Modules\Imports\Repositories\ImportResultRepository;
use App\Modules\Imports\Services\ImportTypeRegistry;
use App\Modules\Imports\Services\SpreadsheetReader;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Orchestrates the client-import workflow: exposing import types and field
 * definitions, parsing an uploaded file into a job, dispatching the async
 * processor, and reporting status/history/results.
 */
class ImportService
{
    public function __construct(
        private ImportTypeRegistry $registry,
        private ImportJobRepository $jobRepository,
        private ImportResultRepository $resultRepository,
        private SpreadsheetReader $reader,
    ) {
    }

    /**
     * List the available import types for the type-selection UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTypes(): array
    {
        return array_map(function ($handler) {
            return [
                'key' => $handler->key(),
                'label' => $handler->label(),
                'description' => $handler->description(),
                'supportedFileTypes' => ['csv', 'xlsx', 'xls'],
                'maxFileSize' => ImportConstant::MAX_FILE_SIZE_KB * 1024,
            ];
        }, $this->registry->all());
    }

    /**
     * Get the field definitions and options for an import type.
     *
     * @param string $type
     * @return array{importFields: array<int, array<string, mixed>>, importOptions: array<int, array<string, mixed>>}
     */
    public function getFieldsAndOptions(string $type): array
    {
        $handler = $this->registry->get($type);

        return [
            'importFields' => array_map(fn ($field) => $field->toArray(), $handler->fields()),
            'importOptions' => $handler->options(),
        ];
    }

    /**
     * Store an uploaded file, detect its headers and row count, and create a
     * pending import job the user can then map and execute.
     *
     * @param GenericData $genericData Validated request data (file, importType) + userData.
     * @return ImportUploadResult
     * @throws \InvalidArgumentException When the import type is unsupported.
     */
    public function upload(GenericData $genericData): ImportUploadResult
    {
        $data = $genericData->getData();
        /** @var UploadedFile $file */
        $file = $data->file;
        $type = $data->importType;
        $accountId = (int) $genericData->userData->account_id;
        $createdBy = $genericData->userData->id ? (int) $genericData->userData->id : null;

        $handler = $this->registry->get($type);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');
        $rows = $this->reader->read($file->getRealPath(), $extension);

        $headers = [];
        if (!empty($rows)) {
            foreach ($rows[0] as $header) {
                $headers[] = is_string($header) ? trim($header) : (string) $header;
            }
        }

        $totalRows = 0;
        foreach (array_slice($rows, 1) as $row) {
            if ($this->rowHasValue($row)) {
                $totalRows++;
            }
        }

        $storedPath = $file->store("imports/{$accountId}", config('filesystems.default'));

        $job = $this->jobRepository->create([
            'account_id' => $accountId,
            'import_type' => $type,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'status' => ImportConstant::STATUS_PENDING,
            'total_rows' => $totalRows,
            'file_headers' => $headers,
            'created_by' => $createdBy,
        ]);

        $result = new ImportUploadResult();
        $result->importJobId = (int) $job->id;
        $result->fileHeaders = $headers;
        $result->totalRows = $totalRows;
        $result->importFields = array_map(fn ($field) => $field->toArray(), $handler->fields());
        $result->importOptions = $handler->options();

        return $result;
    }

    /**
     * Persist the chosen column mapping and dispatch the async processor.
     *
     * @param GenericData $genericData Validated request data (importJobId, columnMapping, options) + userData.
     * @return ImportJob
     * @throws \RuntimeException When the job is missing or required fields are unmapped.
     */
    public function execute(GenericData $genericData): ImportJob
    {
        $data = $genericData->getData();
        $jobId = (int) $data->importJobId;
        $columnMapping = (array) ($data->columnMapping ?? []);
        $options = $data->options ?? null;
        $accountId = (int) $genericData->userData->account_id;

        $job = $this->jobRepository->findForAccount($jobId, $accountId);
        if (!$job) {
            throw new \RuntimeException('Import job not found.');
        }

        $handler = $this->registry->get($job->import_type);

        $missing = [];
        foreach ($handler->fields() as $field) {
            if ($field->required && empty($columnMapping[$field->key])) {
                $missing[] = $field->label;
            }
        }
        if (!empty($missing)) {
            throw new \RuntimeException('Please map all required fields: ' . implode(', ', $missing) . '.');
        }

        $job = $this->jobRepository->update($job, [
            'column_mapping' => $columnMapping,
            'options' => $options,
            'status' => ImportConstant::STATUS_PROCESSING,
            'started_at' => now(),
            'processed_rows' => 0,
            'success_count' => 0,
            'failure_count' => 0,
            'skipped_count' => 0,
            'error_message' => null,
            'result_file_path' => null,
        ]);

        ProcessImportJob::dispatch((int) $job->id)->onQueue('default');

        return $job;
    }

    /**
     * Get the current status of an import job for an account.
     *
     * @param int $jobId
     * @param GenericData $genericData
     * @return ImportJob|null
     */
    public function getStatus(int $jobId, GenericData $genericData): ?ImportJob
    {
        return $this->jobRepository->findForAccount($jobId, (int) $genericData->userData->account_id);
    }

    /**
     * Paginated import history for an account.
     *
     * @param GenericData $genericData
     * @return LengthAwarePaginator
     */
    public function getHistory(GenericData $genericData): LengthAwarePaginator
    {
        return $this->jobRepository->getHistory($genericData);
    }

    /**
     * Failed row results for a job.
     *
     * @param int $jobId
     * @param GenericData $genericData
     * @return Collection
     */
    public function getFailedResults(int $jobId, GenericData $genericData): Collection
    {
        return $this->resultRepository->getFailedForJob($jobId, (int) $genericData->userData->account_id);
    }

    /**
     * Build a download response for a job's error file, or null when there is none.
     *
     * @param int $jobId
     * @param GenericData $genericData
     * @return StreamedResponse|null
     */
    public function downloadResultFile(int $jobId, GenericData $genericData): ?StreamedResponse
    {
        $job = $this->jobRepository->findForAccount($jobId, (int) $genericData->userData->account_id);
        if (!$job || !$job->result_file_path) {
            return null;
        }

        $disk = config('filesystems.default');
        if (!Storage::disk($disk)->exists($job->result_file_path)) {
            return null;
        }

        $base = pathinfo($job->original_filename, PATHINFO_FILENAME) ?: 'import';

        return Storage::disk($disk)->download($job->result_file_path, "{$base}-import-errors.csv");
    }

    /**
     * Whether a spreadsheet row has at least one non-empty cell.
     *
     * @param array<int, mixed> $row
     * @return bool
     */
    private function rowHasValue(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }
        return false;
    }
}
