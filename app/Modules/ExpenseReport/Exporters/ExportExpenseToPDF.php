<?php

namespace App\Modules\ExpenseReport\Exporters;

use App\Constants\PdfExportConstant;
use App\Dtos\Core\ExpenseReportDto;
use App\Modules\ExpenseReport\ExpenseReportExportInterface;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ExportExpenseToPDF implements ExpenseReportExportInterface
{
    private ExportExpenseService $exportExpenseService;

    public function __construct(ExportExpenseService $exportExpenseService)
    {
        $this->exportExpenseService = $exportExpenseService;
    }

    public function export(ExpenseReportDto $expenseReportDto, Collection $expenseData)
    {
        $expenseLength = $expenseData->count();
        if ($expenseLength > PdfExportConstant::MAXIMUM_EXPORT_LIMIT) {
            return response()->json([
                'error' => 'The report contains too many records. Please reduce the data set and try again.',
            ], 400);
        }

        $records = $this->exportExpenseService->transformData($expenseData);
        $summaryHeaderData = $this->exportExpenseService->getSummaryHeaderData($expenseData);
        $headers = $this->exportExpenseService->getHeaders();
        $periodLabel = $expenseReportDto->getPeriodLabel() ?? "{$expenseReportDto->getDateFrom()} – {$expenseReportDto->getDateTo()}";
        $generatedAt = Carbon::now()->toDateTimeString();

        $html = $this->buildHtml($summaryHeaderData, $headers, $records, $periodLabel, $generatedAt);
        $pdf = PdfFacade::loadHTML($html)->setPaper('a4', 'landscape');

        return $pdf->stream('expense-report-' . $expenseReportDto->getDateFrom() . '.pdf');
    }

    private function buildHtml(array $summaryHeaderData, array $headers, array $records, string $periodLabel, string $generatedAt): string
    {
        $title = htmlspecialchars($summaryHeaderData['title'] ?? 'Expense Report');
        $businessName = htmlspecialchars($summaryHeaderData['businessName'] ?? '');
        $summaryRows = $summaryHeaderData['summaryRows'] ?? [];

        $summaryHtml = '';
        if (!empty($summaryRows)) {
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
        foreach ($records as $row) {
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
