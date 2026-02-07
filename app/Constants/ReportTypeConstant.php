<?php

namespace App\Constants;

class ReportTypeConstant
{
    const COLLECTION = 'collection';
    const EXPENSE = 'expense';
    const SUMMARY = 'summary';

    /**
     * Get all valid report types as array
     *
     * @return array<string>
     */
    public static function getAll(): array
    {
        return [
            self::COLLECTION,
            self::EXPENSE,
            self::SUMMARY,
        ];
    }

    /**
     * Get validation rule string for report types
     *
     * @return string
     */
    public static function getValidationRule(): string
    {
        return implode(',', self::getAll());
    }
}
