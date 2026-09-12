<?php

namespace App\Http\Controllers\Platform;

use App\Helpers\ApiResponse;
use App\Helpers\PlatformResponse;
use App\Http\Requests\Platform\PlatformAccountIndexRequest;
use App\Http\Resources\Platform\PlatformAccountDetailResource;
use App\Http\Resources\Platform\PlatformAccountResource;
use App\Services\Platform\PlatformAccountService;
use Illuminate\Http\JsonResponse;

/**
 * The subscriber accounts of this product, as the console browses them.
 */
class PlatformAccountController
{
    public function __construct(
        private PlatformAccountService $accountService,
    ) {
    }

    /**
     * @param PlatformAccountIndexRequest $request
     *
     * @return JsonResponse
     */
    public function getAccounts(PlatformAccountIndexRequest $request): JsonResponse
    {
        return PlatformResponse::paginated(
            $this->accountService->getAccounts($request->filters(), $request->page(), $request->limit()),
            PlatformAccountResource::class,
        );
    }

    /**
     * @param int $accountId
     *
     * @return JsonResponse
     */
    public function getAccountDetail(int $accountId): JsonResponse
    {
        $account = $this->accountService->getAccountDetail($accountId);

        if ($account === null) {
            return ApiResponse::error('Account not found.', 404);
        }

        return PlatformResponse::item(new PlatformAccountDetailResource($account));
    }
}
