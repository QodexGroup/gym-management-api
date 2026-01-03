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
            'relations' => ['nullable', 'string'],
            'sorts' => ['nullable', 'array'],
            'customerId' => ['nullable', 'integer'],
        ];
    }

    /**
     * Prepare the data for validation.
     * Set default values for page and pagelimit if not provided.
     * Parse JSON strings for relations, filters, and sorts.
     */
    protected function prepareForValidation(): void
    {
        $data = [
            'page' => $this->input('page', 1),
            'pagelimit' => $this->input('pagelimit', 50),
        ];


        if ($this->has('filters')) {
            $filters = $this->input('filters');
            if (is_string($filters)) {
                $decoded = json_decode($filters, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data['filters'] = $decoded;
                } else {
                    $data['filters'] = [];
                }
            } else {
                $data['filters'] = $filters;
            }
        }

        if ($this->has('sorts')) {
            $sorts = $this->input('sorts');
            if (is_string($sorts)) {
                $decoded = json_decode($sorts, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data['sorts'] = $decoded;
                } else {
                    $data['sorts'] = [];
                }
            } else {
                $data['sorts'] = $sorts;
            }
        }

        $this->merge($data);
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

        // Parse relations as comma-separated string and explode to array
        $relations = $this->input('relations');
        if (is_string($relations) && !empty($relations)) {
            $params->relations = array_map('trim', explode(',', $relations));
        } else {
            $params->relations = [];
        }

        $params->sorts = $this->input('sorts', []);
        $params->customerId = $this->input('customerId') ? (int)$this->input('customerId') : null;

        // Also handle single 'sort' parameter for backward compatibility
        if ($this->getSort() && empty($params->sorts)) {
            $params->sorts = [$this->getSort()];
        }

        return $params;
    }
}

