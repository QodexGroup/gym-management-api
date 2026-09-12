<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One runnable command as the console's drawer renders it.
 *
 * `command` — the underlying artisan signature — is deliberately absent. The
 * console addresses commands by their stable `key`; publishing the real name
 * would invite a caller to try running it directly, and it is the one detail an
 * operator has no use for.
 *
 * @mixin array
 */
class PlatformCommandResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource['key'],
            'label' => $this->resource['label'],
            'description' => $this->resource['description'],
            'scope' => $this->resource['scope'],
            'destructive' => $this->resource['destructive'],
            'timeout' => $this->resource['timeout'],
            'params' => array_map(static fn (array $param): array => [
                'name' => $param['name'],
                'label' => $param['label'],
                'input' => $param['input'],
                'required' => $param['required'],
                'hint' => $param['hint'],
            ], $this->resource['params']),
        ];
    }
}
