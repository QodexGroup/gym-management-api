<?php

namespace App\Services\Core\Export;

use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reusable Excel export: generates a report XLSX from payload (title, summaryRows, headers, rows).
 * Same layout as frontend reportPrintExport (receipt-style: header, summary, detail table).
 */
class ExportExcel
{
    /**
     * Generate Excel file from report payload. Returns full path to saved file.
     *
     * @param array{title: string, businessName: string, periodLabel: string, generatedAt: string, summaryRows: array, headers: array, rows: array, sheetName: string} $payload
     * @param string|null $filename optional filename (e.g. report.xlsx)
     * @return string full path to generated file
     */
    public function generate(array $payload, ?string $filename = null): string
    {
        $filename = $filename ?? 'report-' . uniqid() . '.xlsx';
        $path = 'reports/' . $filename;

        $export = new ReportToExcelExport($payload);
        Excel::store($export, $path);

        return Storage::path($path);
    }
}
