<?php

namespace App\Modules\Imports\Contracts;

use App\Modules\Imports\Data\ImportRowOutcome;

/**
 * Contract every import type (client, and future types) must implement.
 * The registry resolves handlers by their key(); the service uses fields()
 * to drive the mapping UI and importRow() to process each parsed row.
 */
interface ImportTypeHandler
{
    /**
     * The unique registry key for this import type (e.g. 'client').
     *
     * @return string
     */
    public function key(): string;

    /**
     * Human-readable label for the import type.
     *
     * @return string
     */
    public function label(): string;

    /**
     * Short description shown on the import type card.
     *
     * @return string
     */
    public function description(): string;

    /**
     * The mappable field definitions, required fields first then optional.
     *
     * @return array<int, \App\Modules\Imports\Support\FieldDefinition>
     */
    public function fields(): array;

    /**
     * Extra import options (dynamic settings). Empty for the client import.
     *
     * @return array<int, array<string, mixed>>
     */
    public function options(): array;

    /**
     * Validate and persist a single mapped row.
     *
     * @param array<string, mixed> $mapped Field key => value for this row.
     * @param int $accountId
     * @param int|null $createdBy
     * @return ImportRowOutcome
     */
    public function importRow(array $mapped, int $accountId, ?int $createdBy = null): ImportRowOutcome;
}
