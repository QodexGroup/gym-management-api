<?php

namespace App\Helpers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The platform contract's response envelope: `{ data, meta? }`.
 *
 * This is deliberately NOT ApiResponse. The rest of GymHub answers with
 * `{success, message?, data?}` and puts pagination inside `data`; the console
 * reads a top-level `meta`. Changing ApiResponse to suit one consumer would
 * alter every existing endpoint, so the platform surface gets its own envelope
 * and the two stay independent.
 *
 * Errors still go through ApiResponse::error — its `{success:false, message}`
 * already carries the `message` the console reads, and an extra key is harmless.
 */
class PlatformResponse
{
    /**
     * A single object. No `meta` — the contract omits it for single resources.
     *
     * @param mixed $data
     * @param int $statusCode
     *
     * @return JsonResponse
     */
    public static function item($data, int $statusCode = 200): JsonResponse
    {
        return response()->json(['data' => $data], $statusCode);
    }

    /**
     * A collection, optionally with pagination metadata.
     *
     * @param mixed $data
     * @param array<string, mixed>|null $meta
     *
     * @return JsonResponse
     */
    public static function collection($data, ?array $meta = null): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload);
    }

    /**
     * A paginated collection, mapped through a JsonResource class.
     *
     * Laravel's own pagination payload is not used: it nests `meta` inside
     * `data` when wrapped, and names things differently (`per_page`,
     * `current_page`). The contract asks for `page`, `limit`, `total`.
     *
     * @param LengthAwarePaginator $paginator
     * @param class-string<JsonResource> $resourceClass
     *
     * @return JsonResponse
     */
    public static function paginated(LengthAwarePaginator $paginator, string $resourceClass): JsonResponse
    {
        return self::collection(
            $resourceClass::collection($paginator->getCollection())->resolve(),
            [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        );
    }
}
