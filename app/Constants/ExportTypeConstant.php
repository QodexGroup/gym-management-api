<?php

namespace App\Constants;

class ExportTypeConstant
{
    const PDF = 'pdf';
    const EXCEL = 'xlsx';
    const REQUEST_FORMAT_EXCEL = 'excel';

    /**
     * Normalize request format to export type
     * Converts 'excel' to 'xlsx' and keeps 'pdf' as is
     *
     * @param string $format
     * @return string
     */
    public static function normalizeFormat(string $format): string
    {
        return $format === self::REQUEST_FORMAT_EXCEL ? self::EXCEL : self::PDF;
    }

    /**
     * Get file extension for export type
     *
     * @param string $exportType
     * @return string
     */
    public static function getFileExtension(string $exportType): string
    {
        return $exportType === self::EXCEL ? self::EXCEL : self::PDF;
    }
}
