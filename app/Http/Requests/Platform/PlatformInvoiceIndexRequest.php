<?php

namespace App\Http\Requests\Platform;

use App\Constant\PlatformConstant;
use Illuminate\Validation\Rule;

/**
 * Filters for the invoice list. `overdue` is accepted even though no row is
 * ever stored with that status — it is derived from the billing calendar.
 */
class PlatformInvoiceIndexRequest extends PlatformPaginatedRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::in(PlatformConstant::INVOICE_STATUSES)],
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
