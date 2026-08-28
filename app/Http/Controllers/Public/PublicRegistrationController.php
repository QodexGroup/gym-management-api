<?php

namespace App\Http\Controllers\Public;

use App\Constant\PublicRegistrationConstant;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\PublicRegistrationRequest;
use App\Models\Account\Account;
use App\Services\Public\PublicRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Public member self-registration.
 *
 * Unauthenticated and rate limited. The account is resolved from the URL
 * public_code by ResolvePublicAccountMiddleware.
 */
class PublicRegistrationController extends Controller
{
    /**
     * @param PublicRegistrationService $publicRegistrationService
     */
    public function __construct(
        private PublicRegistrationService $publicRegistrationService,
    ) {
    }

    /**
     * Create a member profile from a public submission.
     *
     * The response is deliberately thin: a first name and the gym's own
     * confirmation copy. It must never return the customer id or
     * qr_code_uuid — that would hand anyone who filled in the form a working
     * check-in credential without a human ever confirming who they are. The
     * front desk issues the QR after verifying them in person.
     *
     * A duplicate phone number raises a RuntimeException from the service,
     * which bootstrap/app.php renders as a 422 carrying its message.
     *
     * @param PublicRegistrationRequest $request
     * @param string $publicCode Present for route-model clarity; the account comes from the middleware.
     * @return JsonResponse
     */
    public function createRegistration(PublicRegistrationRequest $request, string $publicCode): JsonResponse
    {
        /** @var Account $account */
        $account = $request->attributes->get('public_account');
        $validated = $request->validated();

        if ($this->isHoneypotTriggered($request->input(PublicRegistrationConstant::HONEYPOT_FIELD))) {
            Log::info('Public registration honeypot triggered', [
                'account_id' => $account->id,
                'ip' => $request->ip(),
            ]);

            return $this->successResponse($account, (string) ($validated['firstName'] ?? ''));
        }

        $customer = $this->publicRegistrationService->createRegistration(
            $account,
            $validated,
            (string) $request->ip()
        );

        return $this->successResponse($account, (string) $customer->first_name, 201);
    }

    /**
     * Whether the hidden honeypot field was filled in.
     *
     * @param mixed $value
     * @return bool
     */
    private function isHoneypotTriggered(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /**
     * The confirmation payload, identical whether a record was written or the
     * honeypot silently swallowed the submission.
     *
     * @param Account $account
     * @param string $firstName
     * @param int $statusCode
     * @return JsonResponse
     */
    private function successResponse(Account $account, string $firstName, int $statusCode = 200): JsonResponse
    {
        $info = $this->publicRegistrationService->getGymRegistrationInfo($account);

        return ApiResponse::success([
            'firstName' => $firstName,
            'gymName' => $info->gymName,
            'successText' => $info->successText,
        ], null, $statusCode);
    }
}
