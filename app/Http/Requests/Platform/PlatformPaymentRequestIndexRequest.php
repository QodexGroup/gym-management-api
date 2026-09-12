<?php

namespace App\Http\Requests\Platform;

use App\Constant\AccountPaymentRequestStatusConstant;
use Illuminate\Validation\Rule;

/**
 * Filters for the payment request list.
 */
class PlatformPaymentRequestIndexRequest extends PlatformPaginatedRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::in([
                AccountPaymentRequestStatusConstant::STATUS_PENDING,
                AccountPaymentRequestStatusConstant::STATUS_APPROVED,
                AccountPaymentRequestStatusConstant::STATUS_REJECTED,
            ])],
            'accountId' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter(
            $this->only(['status', 'accountId']),
            static fn ($value) => $value !== null && $value !== '',
        );
    }
}
