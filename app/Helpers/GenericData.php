<?php

namespace App\Helpers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class GenericData
{
    public ?User $userData = null;
    public array $filters = [];
    public array $relations = [];
    public array $sorts = [];
    public int $pageSize = 50;
    public int $page = 1;
    public array $data = [];
    public ?int $customerId = null;

    private ?\stdClass $dataObject = null;

    /**
     * Get data as an object for property access
     *
     * @return \stdClass
     */
    public function getData(): \stdClass
    {
        if ($this->dataObject === null) {
            $this->dataObject = (object) $this->data;
        } else {
            // Sync object with latest data
            foreach ($this->data as $key => $value) {
                $this->dataObject->$key = $value;
            }
        }
        return $this->dataObject;
    }

    /**
     * Sync data object back to array (call after modifying getData() properties)
     *
     * @return void
     */
    public function syncDataArray(): void
    {
        if ($this->dataObject !== null) {
            $this->data = (array) $this->dataObject;
        }
    }

    /**
     * Apply filters to a query builder.
     *
     * @param Builder $query
     * @return Builder
     */
    public function applyFilters(Builder $query): Builder
    {
        foreach ($this->filters as $field => $value) {
            if (is_array($value)) {
                // Handle array filters (e.g., ['status' => ['active', 'inactive']])
                if (isset($value['operator']) && isset($value['value'])) {
                    $operator = $value['operator'];
                    $filterValue = $value['value'];
                    switch ($operator) {
                        case 'like':
                            $query->where($field, 'LIKE', "%{$filterValue}%");
                            break;
                        case 'in':
                            $query->whereIn($field, is_array($filterValue) ? $filterValue : [$filterValue]);
                            break;
                        case 'between':
                            $query->whereBetween($field, $filterValue);
                            break;
                        default:
                            $query->where($field, $operator, $filterValue);
                            break;
                    }
                } else {
                    $query->whereIn($field, $value);
                }
            } else {
                $query->where($field, $value);
            }
        }

        return $query;
    }

    /**
     * Apply sorting to a query builder.
     * Supports relation fields in format: "relationName.fieldName"
     *
     * @param Builder $query
     * @param string $defaultField
     * @param string $defaultDirection
     * @return Builder
     */
    public function applySorts(Builder $query, string $defaultField = 'updated_at', string $defaultDirection = 'desc'): Builder
    {
        if (!empty($this->sorts)) {
            $model = $query->getModel();
            $mainTable = $model->getTable();
            $joinedTables = [];

            foreach ($this->sorts as $sort) {
                $field = null;
                $direction = 'asc';

                // Parse sort format
                if (is_string($sort)) {
                    // Handle string format: "field:direction" or just "field"
                    $parts = explode(':', $sort);
                    $field = $parts[0];
                    $direction = $parts[1] ?? 'asc';
                } elseif (is_array($sort)) {
                    // Handle array format: ['field' => 'direction'] or ['field' => 'fieldName', 'direction' => 'asc']
                    if (isset($sort['field']) && isset($sort['direction'])) {
                        $field = $sort['field'];
                        $direction = $sort['direction'];
                    } else {
                        // Legacy format: ['fieldName' => 'direction']
                        $field = key($sort);
                        $direction = $sort[$field];
                    }
                }

                if (!$field) {
                    continue;
                }

                // Check if field contains a relation (e.g., "relationName.fieldName")
                if (strpos($field, '.') !== false) {
                    [$relationName, $relationField] = explode('.', $field, 2);

                    // Get the relation
                    if (method_exists($model, $relationName)) {
                        $relation = $model->{$relationName}();
                        $relatedModel = $relation->getRelated();
                        $relatedTable = $relatedModel->getTable();

                        // Get foreign key and owner key based on relation type
                        $foreignKey = null;
                        $ownerKey = null;

                        // Check if it's a BelongsTo relation
                        if (method_exists($relation, 'getForeignKeyName')) {
                            // BelongsTo: foreign key is on current model, owner key is on related model
                            // Join: mainTable.foreignKey = relatedTable.ownerKey
                            $foreignKey = $relation->getForeignKeyName();
                            $ownerKey = method_exists($relation, 'getOwnerKeyName')
                                ? $relation->getOwnerKeyName()
                                : $relatedModel->getKeyName();

                            $joinLeft = "{$mainTable}.{$foreignKey}";
                            $joinRight = "{$relatedTable}.{$ownerKey}";
                        } elseif (method_exists($relation, 'getQualifiedForeignKeyName')) {
                            // HasOne/HasMany: foreign key is on related model, local key is on current model
                            // Join: relatedTable.foreignKey = mainTable.localKey
                            $qualifiedForeignKey = $relation->getQualifiedForeignKeyName();
                            $qualifiedLocalKey = $relation->getQualifiedLocalKeyName();

                            // Extract column names (remove table prefix)
                            $foreignKeyParts = explode('.', $qualifiedForeignKey);
                            $localKeyParts = explode('.', $qualifiedLocalKey);

                            $foreignKeyColumn = $foreignKeyParts[1] ?? $foreignKeyParts[0];
                            $localKeyColumn = $localKeyParts[1] ?? $localKeyParts[0];

                            $joinLeft = "{$relatedTable}.{$foreignKeyColumn}";
                            $joinRight = "{$mainTable}.{$localKeyColumn}";
                        } else {
                            // Fallback
                            $foreignKey = $model->getForeignKey();
                            $ownerKey = $relatedModel->getKeyName();
                            $joinLeft = "{$mainTable}.{$foreignKey}";
                            $joinRight = "{$relatedTable}.{$ownerKey}";
                        }

                        // Join the table if not already joined
                        if (!in_array($relatedTable, $joinedTables) && isset($joinLeft) && isset($joinRight)) {
                            $query->leftJoin($relatedTable, $joinLeft, '=', $joinRight);
                            $joinedTables[] = $relatedTable;
                        }

                        // Order by the related table field
                        $query->orderBy("{$relatedTable}.{$relationField}", $direction);
                    } else {
                        // Relation doesn't exist, try direct field access
                        $query->orderBy("{$mainTable}.{$field}", $direction);
                    }
                } else {
                    // Direct field, use main table
                    $query->orderBy("{$mainTable}.{$field}", $direction);
                }
            }
        } else {
            // Default sorting
            $query->orderBy($defaultField, $defaultDirection);
        }

        return $query;
    }

    /**
     * Apply relations to a query builder.
     *
     * @param Builder $query
     * @param array $defaultRelations
     * @return Builder
     */
    public function applyRelations(Builder $query, array $defaultRelations = []): Builder
    {
        $relations = !empty($this->relations) ? $this->relations : $defaultRelations;
        if (!empty($relations)) {
            $query->with($relations);
        }
        return $query;
    }
}

