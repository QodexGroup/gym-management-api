<?php

namespace App\Http\Controllers\Platform;

use App\Helpers\ApiResponse;
use App\Helpers\PlatformResponse;
use App\Http\Requests\Platform\PlatformRunCommandRequest;
use App\Http\Resources\Platform\PlatformCommandResource;
use App\Services\Platform\PlatformCommandService;
use Illuminate\Http\JsonResponse;

/**
 * The maintenance commands this product lets the console run.
 *
 * The console discovers this list at runtime rather than holding its own copy,
 * which is what lets one product expose a command another has never heard of
 * without either side erroring.
 */
class PlatformCommandController
{
    public function __construct(
        private PlatformCommandService $commandService,
    ) {
    }

    /**
     * Every allowlisted command, global and account-scoped together. The console
     * splits them by `scope`.
     *
     * @return JsonResponse
     */
    public function getCommands(): JsonResponse
    {
        return PlatformResponse::collection(
            PlatformCommandResource::collection($this->commandService->getCommands())->resolve(),
        );
    }

    /**
     * Run one command.
     *
     * An unknown key is a 404 rather than a 422: from the console's side the
     * command genuinely does not exist on this product, which is exactly what
     * happens when a key from another product is tried here.
     *
     * @param PlatformRunCommandRequest $request
     * @param string $commandKey
     *
     * @return JsonResponse
     */
    public function runCommand(PlatformRunCommandRequest $request, string $commandKey): JsonResponse
    {
        $definition = $this->commandService->findCommand($commandKey);

        if ($definition === null) {
            return ApiResponse::error("This product does not expose a command called \"{$commandKey}\".", 404);
        }

        return PlatformResponse::item($this->commandService->runCommand(
            $definition,
            $request->accountId(),
            $request->params(),
        ));
    }
}
