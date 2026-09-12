<?php

namespace App\Http\Requests\Platform;

use App\Constant\PlatformConstant;
use Illuminate\Validation\Rule;

/**
 * Filters for the account list.
 */
class PlatformAccountIndexRequest extends PlatformPaginatedRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::in(PlatformConstant::ACCOUNT_STATUSES)],
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],
            'createdFrom' => ['sometimes', 'nullable', 'date'],
            'createdTo' => ['sometimes', 'nullable', 'date', 'after_or_equal:createdFrom'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter(
            $this->only(['status', 'search', 'createdFrom', 'createdTo']),
            static fn ($value) => $value !== null && $value !== '',
        );
    }
}
