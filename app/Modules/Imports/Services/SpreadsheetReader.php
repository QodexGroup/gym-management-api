<?php

namespace App\Modules\Imports\Services;

use App\Modules\Imports\Support\RawSheetImport;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reads a CSV/Excel file from an absolute local path into a plain array of
 * rows (first row = headers). Used both at upload (headers + row count) and
 * during processing (full data).
 */
class SpreadsheetReader
{
    /**
     * Parse the first sheet of a spreadsheet into an array of rows.
     *
     * @param string $absolutePath Local filesystem path to the file.
     * @param string $extension File extension (csv, txt, xlsx, xls) used to pick the reader.
     * @return array<int, array<int, mixed>> Rows; each row is a numeric-indexed array of cell values.
     */
    public function read(string $absolutePath, string $extension): array
    {
        $readerType = $this->readerType($extension);
        $sheets = Excel::toArray(new RawSheetImport(), $absolutePath, null, $readerType);
        return $sheets[0] ?? [];
    }

    /**
     * Map a file extension to a Maatwebsite reader type.
     *
     * @param string $extension
     * @return string
     */
    private function readerType(string $extension): string
    {
        return match (strtolower($extension)) {
            'xlsx' => ExcelFormat::XLSX,
            'xls' => ExcelFormat::XLS,
            default => ExcelFormat::CSV,
        };
    }
}
