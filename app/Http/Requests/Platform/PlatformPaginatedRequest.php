<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared paging for the platform contract's list endpoints.
 *
 * `limit` is clamped rather than merely validated: the console is trusted, but
 * a mistyped limit should not be able to ask this product to serialise every
 * account it has into one response.
 */
abstract class PlatformPaginatedRequest extends FormRequest
{
    /**
     * Authorisation is the service token, checked by middleware before this
     * request is ever constructed.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1'],
        ] + $this->filterRules();
    }

    /**
     * Rules specific to one endpoint's filters.
     *
     * @return array<string, mixed>
     */
    abstract protected function filterRules(): array;

    /**
     * Filters to hand to the repository.
     *
     * @return array<string, mixed>
     */
    abstract public function filters(): array;

    /**
     * @return int
     */
    public function page(): int
    {
        return max(1, (int) $this->input('page', 1));
    }

    /**
     * @return int
     */
    public function limit(): int
    {
        $limit = (int) $this->input('limit', config('platform.page_size', 25));

        return max(1, min($limit, (int) config('platform.max_page_size', 100)));
    }
}
