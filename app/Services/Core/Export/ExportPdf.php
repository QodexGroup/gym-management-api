<?php

namespace App\Services\Core\Export;

use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Illuminate\Support\Facades\Storage;

/**
 * Reusable PDF export: generates a report PDF from payload (title, summaryRows, headers, rows).
 * Same layout as frontend reportPrintExport (receipt-style: header, summary, detail table).
 */
class ExportPdf
{
    /**
     * Generate PDF file from report payload. Returns full path to saved file.
     *
     * @param array{title: string, businessName: string, periodLabel: string, generatedAt: string, summaryRows: array, headers: array, rows: array} $payload
     * @param string|null $filename optional filename (without path)
     * @return string full path to generated PDF
     */
    public function generate(array $payload, ?string $filename = null): string
    {
        $filename = $filename ?? 'report-' . uniqid() . '.pdf';
        $path = 'reports/' . $filename;
        $fullPath = Storage::path($path);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $html = $this->buildHtml($payload);
        PdfFacade::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->save($fullPath);

        return $fullPath;
    }

    /**
     * Build HTML for receipt-style report (business, title, period, summary table, detail table).
     */
    private function buildHtml(array $payload): string
    {
        $title = htmlspecialchars($payload['title'] ?? 'Report');
        $businessName = htmlspecialchars($payload['businessName'] ?? '');
        $periodLabel = htmlspecialchars($payload['periodLabel'] ?? '');
        $generatedAt = htmlspecialchars($payload['generatedAt'] ?? '');
        $summaryRows = $payload['summaryRows'] ?? [];
        $headers = $payload['headers'] ?? [];
        $rows = $payload['rows'] ?? [];

        $summaryHtml = '';
        if (! empty($summaryRows)) {
            $summaryHtml = '<table style="margin-bottom:16px; border-collapse:collapse;"><thead><tr><th style="text-align:left; padding:4px 8px; border:1px solid #ddd;">Summary</th><th style="border:1px solid #ddd;"></th></tr></thead><tbody>';
            foreach ($summaryRows as $pair) {
                $summaryHtml .= '<tr><td style="padding:4px 8px; border:1px solid #ddd;">' . htmlspecialchars($pair[0] ?? '') . '</td><td style="padding:4px 8px; border:1px solid #ddd;">' . htmlspecialchars($pair[1] ?? '') . '</td></tr>';
            }
            $summaryHtml .= '</tbody></table>';
        }

        $headerCells = '';
        foreach ($headers as $h) {
            $headerCells .= '<th style="text-align:left; padding:6px 8px; border:1px solid #ddd; background:#0f172a; color:#fff;">' . htmlspecialchars($h) . '</th>';
        }
        $bodyRows = '';
        foreach ($rows as $row) {
            $bodyRows .= '<tr>';
            foreach ($row as $cell) {
                $bodyRows .= '<td style="padding:4px 8px; border:1px solid #ddd;">' . htmlspecialchars((string) $cell) . '</td>';
            }
            $bodyRows .= '</tr>';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; margin: 14px; }
h1 { font-size: 12px; margin: 0 0 6px 0; }
</style>
</head>
<body>
<p style="font-weight:bold; font-size:10px;">{$businessName}</p>
<h1>{$title}</h1>
<p>Period: {$periodLabel}</p>
<p>Generated: {$generatedAt}</p>
{$summaryHtml}
<table style="width:100%; border-collapse:collapse;">
<thead><tr>{$headerCells}</tr></thead>
<tbody>{$bodyRows}</tbody>
</table>
<p style="margin-top:12px; font-size:8px;">Generated: {$generatedAt}</p>
</body>
</html>
HTML;
    }
}
