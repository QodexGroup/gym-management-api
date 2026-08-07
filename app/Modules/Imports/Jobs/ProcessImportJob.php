<?php

namespace App\Modules\Imports\Jobs;

use App\Modules\Imports\Constants\ImportConstant;
use App\Modules\Imports\Models\ImportJob;
use App\Modules\Imports\Repositories\ImportJobRepository;
use App\Modules\Imports\Repositories\ImportResultRepository;
use App\Modules\Imports\Services\ImportTypeRegistry;
use App\Modules\Imports\Services\SpreadsheetReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\Core\NotificationService;

/**
 * Processes an uploaded import file row by row on the queue: applies the saved
 * column mapping, validates and persists each row via the type handler, tracks
 * progress, and writes a downloadable error file for failed/skipped rows.
 */
class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Number of retry attempts (import must not double-insert). */
    public int $tries = 1;

    /** @var int Max seconds the job may run. */
    public int $timeout = 900;

    /**
     * @param int $importJobId
     */
    public function __construct(public int $importJobId)
    {
    }

    /**
     * Run the import.
     *
     * @param ImportTypeRegistry $registry
     * @param ImportJobRepository $jobRepository
     * @param ImportResultRepository $resultRepository
     * @param SpreadsheetReader $reader
     * @param NotificationService $notificationService
     * @return void
     */
    public function handle(
        ImportTypeRegistry $registry,
        ImportJobRepository $jobRepository,
        ImportResultRepository $resultRepository,
        SpreadsheetReader $reader,
        NotificationService $notificationService
    ): void {
        $job = ImportJob::find($this->importJobId);
        if (!$job) {
            return;
        }

        try {
            $handler = $registry->get($job->import_type);

            $jobRepository->update($job, [
                'status' => ImportConstant::STATUS_PROCESSING,
                'started_at' => $job->started_at ?? now(),
                'processed_rows' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'skipped_count' => 0,
            ]);

            $rows = $this->readRows($job, $reader);
            if (empty($rows)) {
                throw new \RuntimeException('The uploaded file has no readable rows.');
            }

            $headerIndex = $this->buildHeaderIndex($rows[0]);
            $fieldColumns = $this->resolveFieldColumns($job->column_mapping ?? [], $headerIndex);

            $fields = $handler->fields();
            $dataRows = array_slice($rows, 1);

            $total = 0;
            foreach ($dataRows as $row) {
                if ($this->rowHasValue($row)) {
                    $total++;
                }
            }
            $jobRepository->update($job, ['total_rows' => $total]);

            $success = 0;
            $failure = 0;
            $skipped = 0;
            $processed = 0;
            $rowNumber = 1; // header occupies row 1

            foreach ($dataRows as $row) {
                $rowNumber++;
                if (!$this->rowHasValue($row)) {
                    continue;
                }

                $mapped = [];
                foreach ($fieldColumns as $fieldKey => $colIndex) {
                    $mapped[$fieldKey] = array_key_exists($colIndex, $row) ? $row[$colIndex] : null;
                }

                $outcome = $handler->importRow(
                    $mapped,
                    (int) $job->account_id,
                    $job->created_by ? (int) $job->created_by : null
                );

                $resultRepository->create([
                    'import_job_id' => $job->id,
                    'account_id' => $job->account_id,
                    'row_number' => $rowNumber,
                    'status' => $outcome->status,
                    'original_data' => $mapped,
                    'errors' => !empty($outcome->errors) ? $outcome->errors : null,
                    'created_record_id' => $outcome->createdRecordId,
                    'message' => $outcome->message,
                ]);

                match ($outcome->status) {
                    ImportConstant::RESULT_SUCCESS => $success++,
                    ImportConstant::RESULT_SKIPPED => $skipped++,
                    default => $failure++,
                };
                $processed++;

                if ($processed % ImportConstant::PROGRESS_FLUSH_EVERY === 0) {
                    $jobRepository->update($job, [
                        'processed_rows' => $processed,
                        'success_count' => $success,
                        'failure_count' => $failure,
                        'skipped_count' => $skipped,
                    ]);
                }
            }

            $resultPath = $this->generateResultFile($job, $fields, $resultRepository);

            $jobRepository->update($job, [
                'status' => ImportConstant::STATUS_COMPLETED,
                'processed_rows' => $processed,
                'success_count' => $success,
                'failure_count' => $failure,
                'skipped_count' => $skipped,
                'result_file_path' => $resultPath,
                'completed_at' => now(),
            ]);

            $notificationService->createImportCompletedNotification((int) $job->account_id, [
                'filename' => $job->original_filename,
                'failed' => false,
                'success' => $success,
                'skipped' => $skipped,
                'failure' => $failure,
                'importJobId' => (int) $job->id,
            ]);

            Log::info('Import job completed', [
                'import_job_id' => $job->id,
                'success' => $success,
                'skipped' => $skipped,
                'failed' => $failure,
            ]);
        } catch (\Throwable $e) {
            Log::error('Import job failed', [
                'import_job_id' => $this->importJobId,
                'error' => $e->getMessage(),
            ]);

            $jobRepository->update($job, [
                'status' => ImportConstant::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            $notificationService->createImportCompletedNotification((int) $job->account_id, [
                'filename' => $job->original_filename,
                'failed' => true,
                'errorMessage' => $e->getMessage(),
                'importJobId' => (int) $job->id,
            ]);
        }
    }

    /**
     * Materialize the stored file locally and parse it into rows.
     *
     * @param ImportJob $job
     * @param SpreadsheetReader $reader
     * @return array<int, array<int, mixed>>
     */
    private function readRows(ImportJob $job, SpreadsheetReader $reader): array
    {
        $disk = config('filesystems.default');
        $extension = strtolower(pathinfo($job->original_filename, PATHINFO_EXTENSION) ?: 'csv');

        $tempPath = tempnam(sys_get_temp_dir(), 'import_') . '.' . $extension;
        file_put_contents($tempPath, Storage::disk($disk)->get($job->file_path));

        try {
            return $reader->read($tempPath, $extension);
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Map each non-empty header string to its column index.
     *
     * @param array<int, mixed> $headerRow
     * @return array<string, int>
     */
    private function buildHeaderIndex(array $headerRow): array
    {
        $index = [];
        foreach ($headerRow as $i => $header) {
            $name = is_string($header) ? trim($header) : (string) $header;
            if ($name !== '') {
                $index[$name] = $i;
            }
        }
        return $index;
    }

    /**
     * Resolve field key => column index from the saved mapping and header index.
     *
     * @param array<string, string|null> $mapping
     * @param array<string, int> $headerIndex
     * @return array<string, int>
     */
    private function resolveFieldColumns(array $mapping, array $headerIndex): array
    {
        $columns = [];
        foreach ($mapping as $fieldKey => $headerName) {
            if ($headerName !== null && $headerName !== '' && array_key_exists($headerName, $headerIndex)) {
                $columns[$fieldKey] = $headerIndex[$headerName];
            }
        }
        return $columns;
    }

    /**
     * Write a CSV of failed/skipped rows to storage; null when everything succeeded.
     *
     * @param ImportJob $job
     * @param array<int, \App\Modules\Imports\Support\FieldDefinition> $fields
     * @param ImportResultRepository $resultRepository
     * @return string|null
     */
    private function generateResultFile(ImportJob $job, array $fields, ImportResultRepository $resultRepository): ?string
    {
        $unsuccessful = $resultRepository->getUnsuccessfulForJob((int) $job->id);
        if ($unsuccessful->isEmpty()) {
            return null;
        }

        $handle = fopen('php://temp', 'r+');
        $columns = array_map(fn ($field) => $field->label, $fields);
        fputcsv($handle, array_merge(['Row', 'Status', 'Reason'], $columns));

        foreach ($unsuccessful as $result) {
            $line = [$result->row_number, $result->status, $result->message];
            foreach ($fields as $field) {
                $line[] = $result->original_data[$field->key] ?? '';
            }
            fputcsv($handle, $line);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $path = "imports/{$job->account_id}/results/{$job->id}-result.csv";
        Storage::disk(config('filesystems.default'))->put($path, $csv);

        return $path;
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
