<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany as BaseBelongsToMany;
use Illuminate\Support\Collection;

trait HasBelongsToManyWithNullCheck
{
    /**
     * Define a many-to-many relationship with null pivot check.
     * This fixes the Laravel bug where BelongsToMany eager loading fails
     * when some records don't have pivot relationships.
     *
     * @param  string  $related
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string|null  $relation
     * @return BelongsToManyWithNullCheck
     */
    public function belongsToManyWithNullCheck(
        $related,
        $table = null,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $parentKey = null,
        $relatedKey = null,
        $relation = null
    ) {
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        if (is_null($table)) {
            $table = $this->joiningTable($related);
        }

        return new BelongsToManyWithNullCheck(
            $instance->newQuery(),
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(),
            $relation ?: $this->guessBelongsToManyRelation()
        );
    }
}

/**
 * Extended BelongsToMany that handles null pivots during eager loading
 */
class BelongsToManyWithNullCheck extends BaseBelongsToMany
{
    /**
     * Build model dictionary keyed by the relation's foreign key.
     * Override to handle null pivots.
     *
     * @param  Collection  $results
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $dictionary = [];

        foreach ($results as $result) {
            // Check if pivot exists and is not null
            // During eager loading, Laravel attaches the pivot as a relation
            $pivot = $result->getRelation($this->accessor) ?? $result->{$this->accessor} ?? null;

            // If pivot doesn't exist or is null, skip this result
            if ($pivot === null) {
                continue;
            }

            // Get the foreign pivot key value from the pivot
            $foreignPivotValue = $pivot->{$this->foreignPivotKey} ?? null;

            // If the foreign pivot key is null, skip this result
            if ($foreignPivotValue === null) {
                continue;
            }

            $value = $this->getDictionaryKey($foreignPivotValue);
            $dictionary[$value][] = $result;
        }

        return $dictionary;
    }
}

