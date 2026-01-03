<?php

namespace App\Traits;

trait HasCamelCaseAttributes
{
    /**
     * Relationships that should be converted to camelCase when accessed.
     * Set this in your model to specify which relationships should be camelCase.
     * Example: protected $camelCaseRelations = ['membershipPlan' => true, 'permissions' => false];
     *
     * @var array<string, bool>
     */
    protected $camelCaseRelations = [];

    /**
     * Convert camelCase keys to snake_case when filling attributes
     */
    public function fill(array $attributes)
    {
        $snakeCaseAttributes = [];
        foreach ($attributes as $key => $value) {
            $snakeCaseKey = $this->camelToSnake($key);
            $snakeCaseAttributes[$snakeCaseKey] = $value;
        }

        return parent::fill($snakeCaseAttributes);
    }

    /**
     * Convert snake_case to camelCase when accessing attributes
     */
    public function getAttribute($key)
    {
        // First, check if it's a relationship method
        if (method_exists($this, $key)) {
            // Check if relationship is already loaded in relations array
            if (array_key_exists($key, $this->relations)) {
                // Check if this relationship should be converted to camelCase
                $shouldConvert = $this->camelCaseRelations[$key] ?? false;
                // If false (default), return as-is (retain snake_case/camelCase as stored)
                // If true, we would convert, but for now just return as-is
                return $this->relations[$key];
            }

            // If not loaded, try to get it (will trigger lazy loading)
            $value = parent::getAttribute($key);
            if ($value !== null) {
                return $value;
            }
        }

        // First try to get the attribute as-is (for regular attributes, etc.)
        $value = parent::getAttribute($key);

        if ($value !== null || $this->hasAttribute($key)) {
            return $value;
        }

        // If not found, try converting camelCase to snake_case
        $snakeKey = $this->camelToSnake($key);
        return parent::getAttribute($snakeKey);
    }

    /**
     * Convert snake_case to camelCase when setting attributes
     */
    public function setAttribute($key, $value)
    {
        $snakeKey = $this->camelToSnake($key);
        return parent::setAttribute($snakeKey, $value);
    }

    /**
     * Convert attributes to array with camelCase keys
     */
    public function toArray()
    {
        $array = parent::toArray();
        $camelCaseArray = [];

        foreach ($array as $key => $value) {
            $camelKey = $this->snakeToCamel($key);
            $camelCaseArray[$camelKey] = $value;
        }

        return $camelCaseArray;
    }

    /**
     * Convert camelCase to snake_case
     */
    protected function camelToSnake(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }

    /**
     * Convert snake_case to camelCase
     */
    protected function snakeToCamel(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }

    /**
     * Check if attribute exists in snake_case format
     */
    public function hasAttribute($key): bool
    {
        $snakeKey = $this->camelToSnake($key);
        return array_key_exists($snakeKey, $this->attributes) ||
               array_key_exists($snakeKey, $this->relations);
    }
}

