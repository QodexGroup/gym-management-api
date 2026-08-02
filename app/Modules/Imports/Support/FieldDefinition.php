<?php

namespace App\Modules\Imports\Support;

/**
 * Immutable description of a single mappable field for an import type.
 * Consumed by the frontend to render the column-mapping UI and by the queue
 * job to validate each incoming row.
 */
class FieldDefinition
{
    /**
     * @param string $key Camel-case field key used in the customer payload (e.g. 'firstName').
     * @param string $label Human-readable label shown in the mapping UI.
     * @param bool $required Whether the user must map a column to this field.
     * @param string $rules Laravel validation rule string applied per row.
     * @param string|null $example Example value shown as a hint / in the template.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $required,
        public readonly string $rules,
        public readonly ?string $example = null,
    ) {
    }

    /**
     * Serialize the definition for the API (snake_case backend contract).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'required' => $this->required,
            'rules' => $this->rules,
            'example' => $this->example,
        ];
    }
}
