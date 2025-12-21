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
     *
     * @param Builder $query
     * @param string $defaultField
     * @param string $defaultDirection
     * @return Builder
     */
    public function applySorts(Builder $query, string $defaultField = 'created_at', string $defaultDirection = 'desc'): Builder
    {
        if (!empty($this->sorts)) {
            foreach ($this->sorts as $sort) {
                if (is_string($sort)) {
                    // Handle string format: "field:direction" or just "field"
                    $parts = explode(':', $sort);
                    $field = $parts[0];
                    $direction = $parts[1] ?? 'asc';
                    $query->orderBy($field, $direction);
                } elseif (is_array($sort)) {
                    // Handle array format: ['field' => 'direction']
                    foreach ($sort as $field => $direction) {
                        $query->orderBy($field, $direction);
                    }
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

