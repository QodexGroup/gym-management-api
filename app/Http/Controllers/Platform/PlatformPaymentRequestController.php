<?php

namespace App\Http\Controllers\Platform;

use App\Helpers\ApiResponse;
use App\Helpers\PlatformResponse;
use App\Http\Middleware\PlatformServiceTokenMiddleware;
use App\Http\Requests\Platform\PlatformPaymentRequestIndexRequest;
use App\Http\Requests\Platform\PlatformRejectPaymentRequest;
use App\Http\Resources\Platform\PlatformPaymentRequestResource;
use App\Services\Platform\PlatformPaymentRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reviewing receipts — the reason the console exists.
 *
 * Both decisions run behind the `idempotent` middleware. A double-clicked
 * approve, or a retried POST, must not settle the same invoice twice and
 * extend a subscriber's coverage twice; the Idempotency-Key the console sends
 * is what prevents that.
 */
class PlatformPaymentRequestController
{
    public function __construct(
        private PlatformPaymentRequestService $paymentRequestService,
    ) {
    }

    /**
     * @param PlatformPaymentRequestIndexRequest $request
     *
     * @return JsonResponse
     */
    public function getPaymentRequests(PlatformPaymentRequestIndexRequest $request): JsonResponse
    {
        return PlatformResponse::paginated(
            $this->paymentRequestService->getPaymentRequests($request->filters(), $request->page(), $request->limit()),
            PlatformPaymentRequestResource::class,
        );
    }

    /**
     * @param Request $request
     * @param int $paymentRequestId
     *
     * @return JsonResponse
     */
    public function approvePaymentRequest(Request $request, int $paymentRequestId): JsonResponse
    {
        return $this->decide(
            fn () => $this->paymentRequestService->approve($paymentRequestId, $this->actor($request)),
        );
    }

    /**
     * @param PlatformRejectPaymentRequest $request
     * @param int $paymentRequestId
     *
     * @return JsonResponse
     */
    public function rejectPaymentRequest(PlatformRejectPaymentRequest $request, int $paymentRequestId): JsonResponse
    {
        return $this->decide(
            fn () => $this->paymentRequestService->reject(
                $paymentRequestId,
                (string) $request->input('reason'),
                $this->actor($request),
            ),
        );
    }

    /**
     * Run a decision and translate its failure modes.
     *
     * AdminPaymentRequestService throws InvalidArgumentException when a request
     * is not pending — which is both "no such request" and "someone already
     * decided this one". bootstrap/app.php only maps RuntimeException to 422,
     * so without this the console would see a 500 and report the product as
     * broken when it had simply refused.
     *
     * @param callable(): \App\Models\Account\AccountPaymentRequest $decision
     *
     * @return JsonResponse
     */
    private function decide(callable $decision): JsonResponse
    {
        try {
            $decided = $decision();
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return PlatformResponse::item(new PlatformPaymentRequestResource($decided));
    }

    /**
     * The console operator, as unpacked from X-Platform-Actor by the service
     * token middleware.
     *
     * @param Request $request
     *
     * @return string|null
     */
    private function actor(Request $request): ?string
    {
        return $request->attributes->get(PlatformServiceTokenMiddleware::ACTOR_ATTRIBUTE);
    }
}
