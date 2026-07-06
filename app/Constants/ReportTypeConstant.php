<?php

namespace App\Constants;

class ReportTypeConstant
{
    const COLLECTION = 'collection';
    const EXPENSE = 'expense';
    const SUMMARY = 'summary';
    const REVENUE = 'revenue';
    const MY_COLLECTION = 'my_collection';
    const MY_REVENUE = 'my_revenue';

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
            self::REVENUE,
            self::MY_COLLECTION,
            self::MY_REVENUE,
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
