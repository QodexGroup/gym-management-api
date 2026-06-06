<?php

namespace App\Repositories;

use App\Helpers\GenericData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

abstract class BaseRepository
{
    /**
     * Apply GenericData relations, filters, and sorts to a query, then paginate.
     * Returns a Collection when pageSize is 0 (fetch-all mode).
     */
    protected function paginateWithGenericData(
        Builder $query,
        GenericData $genericData,
        array $defaultRelations = []
    ): LengthAwarePaginator|Collection {
        $query = $genericData->applyRelations($query, $defaultRelations);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);

        if ($genericData->pageSize > 0) {
            return $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
        }

        return $query->get();
    }

    /**
     * Apply GenericData relations, filters, and sorts to a query without paginating.
     * Use this for methods that always return a full Collection.
     */
    protected function applyGenericData(
        Builder $query,
        GenericData $genericData,
        array $defaultRelations = []
    ): Builder {
        $query = $genericData->applyRelations($query, $defaultRelations);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);
        return $query;
    }
}
