<?php

namespace App\Services\Core\Export;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Maatwebsite Excel export: builds sheet from report payload (same layout as frontend reportPrintExport).
 */
class ReportToExcelExport implements FromArray, WithTitle
{
    public function __construct(
        private array $payload
    ) {
    }

    public function array(): array
    {
        $title = strtoupper($this->payload['title'] ?? 'Report');
        $businessName = $this->payload['businessName'] ?? '';
        $periodLabel = $this->payload['periodLabel'] ?? '';
        $generatedAt = $this->payload['generatedAt'] ?? '';
        $summaryRows = $this->payload['summaryRows'] ?? [];
        $headers = $this->payload['headers'] ?? [];
        $rows = $this->payload['rows'] ?? [];

        $data = [];
        $data[] = [$businessName];
        $data[] = [$title];
        if ($periodLabel !== '') {
            $data[] = ['Period: ' . $periodLabel];
        }
        $data[] = ['Generated: ' . $generatedAt];
        $data[] = [];
        if (! empty($summaryRows)) {
            $data[] = ['Summary', ''];
            foreach ($summaryRows as $pair) {
                $data[] = [$pair[0] ?? '', $pair[1] ?? ''];
            }
            $data[] = [];
        }
        $data[] = $headers;
        foreach ($rows as $row) {
            $data[] = $row;
        }

        return $data;
    }

    public function title(): string
    {
        $sheetName = $this->payload['sheetName'] ?? 'Report';
        return mb_substr($sheetName, 0, 31);
    }
}
