<?php

namespace App\Modules\Imports\Data;

use App\Modules\Imports\Constants\ImportConstant;

/**
 * Result of importing a single row. Returned by an import type handler and
 * persisted as a tb_import_results record by the queue job.
 */
class ImportRowOutcome
{
    public string $status;
    /** @var array<string, array<int, string>> */
    public array $errors = [];
    public ?string $message = null;
    public ?int $createdRecordId = null;

    /**
     * Build a success outcome for a created record.
     *
     * @param int $createdRecordId
     * @return self
     */
    public static function success(int $createdRecordId): self
    {
        $outcome = new self();
        $outcome->status = ImportConstant::RESULT_SUCCESS;
        $outcome->createdRecordId = $createdRecordId;
        return $outcome;
    }

    /**
     * Build a skipped outcome (e.g. duplicate) with a human-readable reason.
     *
     * @param string $message
     * @return self
     */
    public static function skipped(string $message): self
    {
        $outcome = new self();
        $outcome->status = ImportConstant::RESULT_SKIPPED;
        $outcome->message = $message;
        return $outcome;
    }

    /**
     * Build a failed outcome from validation errors.
     *
     * @param array<string, array<int, string>> $errors
     * @param string|null $message
     * @return self
     */
    public static function failed(array $errors, ?string $message = null): self
    {
        $outcome = new self();
        $outcome->status = ImportConstant::RESULT_FAILED;
        $outcome->errors = $errors;
        $outcome->message = $message ?? self::firstError($errors);
        return $outcome;
    }

    /**
     * Extract the first error message from a Laravel error bag array.
     *
     * @param array<string, array<int, string>> $errors
     * @return string|null
     */
    private static function firstError(array $errors): ?string
    {
        foreach ($errors as $messages) {
            if (is_array($messages) && !empty($messages)) {
                return (string) $messages[0];
            }
        }
        return null;
    }
}
