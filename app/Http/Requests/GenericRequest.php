<?php

namespace App\Http\Requests;

use App\Helpers\GenericData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class GenericRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sort' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'pagelimit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'filters' => ['nullable', 'array'],
            'relations' => ['nullable', 'array'],
            'sorts' => ['nullable', 'array'],
            'customerId' => ['nullable', 'integer'],
        ];
    }

    /**
     * Prepare the data for validation.
     * Set default values for page and pagelimit if not provided.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'page' => $this->input('page', 1),
            'pagelimit' => $this->input('pagelimit', 50),
        ]);
    }

    /**
     * Get the authenticated user data.
     *
     * @return \App\Models\User|null
     */
    public function getUserData()
    {
        // Try to get user from request attributes (set by middleware)
        $user = $this->attributes->get('user');

        // Fallback to Auth facade
        if (!$user) {
            $user = Auth::user();
        }

        return $user;
    }

    /**
     * Get the page number.
     *
     * @return int
     */
    public function getPage(): int
    {
        return (int) $this->input('page', 1);
    }

    /**
     * Get the page limit.
     *
     * @return int
     */
    public function getPageLimit(): int
    {
        return (int) $this->input('pagelimit', 50);
    }

    /**
     * Get the sort parameter.
     *
     * @return string|null
     */
    public function getSort(): ?string
    {
        return $this->input('sort');
    }

    /**
     * Get all common request parameters as a GenericData object.
     * Alias for getGenericData() for backward compatibility.
     *
     * @return GenericData
     */
    public function getRequestParams(): GenericData
    {
        return $this->getGenericData();
    }

    /**
     * Get GenericData with validated data included
     *
     * @return GenericData
     */
    public function getGenericDataWithValidated(): GenericData
    {
        $genericData = $this->getGenericData();
        $genericData->data = $this->validated();
        return $genericData;
    }

    /**
     * Get GenericData object with all request parameters.
     *
     * @return GenericData
     */
    public function getGenericData(): GenericData
    {
        $params = new GenericData();
        $params->userData = $this->getUserData();
        $params->page = $this->getPage();
        $params->pageSize = $this->getPageLimit();
        $params->filters = $this->input('filters', []);
        $params->relations = $this->input('relations', []);
        $params->sorts = $this->input('sorts', []);
        $params->customerId = $this->input('customerId') ? (int)$this->input('customerId') : null;

        // Also handle single 'sort' parameter for backward compatibility
        if ($this->getSort() && empty($params->sorts)) {
            $params->sorts = [$this->getSort()];
        }

        return $params;
    }
}

