<?php

namespace App\Services\Account;

use App\Constants\DateFormatConstant;
use App\Constants\ExportTypeConstant;
use App\Constants\ReportTypeConstant;
use App\Helpers\GenericData;
use App\Exports\CollectionReport\CollectionReportSheet;
use App\Exports\ExpenseReport\ExpenseReportSheet;
use App\Exports\SummaryReport\SummaryReportSheet;
use App\Modules\CollectionReport\Exporters\ExportCollectionService;
use App\Modules\ExpenseReport\Exporters\ExportExpenseService;
use App\Modules\SummaryReport\Exporters\ExportSummaryService;
use App\Repositories\Common\ExpenseRepository;
use App\Repositories\Core\CustomerBillRepository;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ReportService
{
    private const MAX_EXPORT_ROWS = 200;

    public function __construct(
        private CustomerBillRepository $customerBillRepository,
        private ExpenseRepository $expenseRepository,
        private ExportCollectionService $exportCollectionService,
        private ExportExpenseService $exportExpenseService,
        private ExportSummaryService $exportSummaryService,
    ) {
    }

    /**
     * Check if report data for the given range is too large for direct export.
     *
     * @param GenericData $genericData GenericData object with validated data in data property
     * @return array{tooLarge: bool, rowCount: int}
     */
    public function checkExportSize(GenericData $genericData): array
    {
        $data = $genericData->getData();
        $accountId = $genericData->userData->account_id;
        $reportType = $data->reportType;
        $dateFrom = $data->dateFrom;
        $dateTo = $data->dateTo;

        switch ($reportType) {
            case ReportTypeConstant::COLLECTION:
                $rowCount = $this->customerBillRepository->countByAccountAndDateRange($accountId, $dateFrom, $dateTo);
                break;
            case ReportTypeConstant::EXPENSE:
            case ReportTypeConstant::SUMMARY:
                $rowCount = $this->expenseRepository->countByAccountAndDateRange($accountId, $dateFrom, $dateTo);
                break;
            default:
                $rowCount = 0;
        }

        return [
            'tooLarge' => $rowCount > self::MAX_EXPORT_ROWS,
            'rowCount' => $rowCount,
        ];
    }

    /**
     * Generate report file and return file path for email attachment
     *
     * @param GenericData $genericData GenericData object with validated data in data property
     * @return \stdClass Object with properties: filePath, title
     */
    public function generateEmailReportFile(GenericData $genericData): \stdClass
    {
        $data = $genericData->getData();
        $accountId = $genericData->userData->account_id;
        $reportType = $data->reportType;
        $dateFrom = $data->dateFrom;
        $dateTo = $data->dateTo;
        $format = $data->format ?? ExportTypeConstant::PDF;
        $periodLabel = $data->dateRange ?? "{$dateFrom}" . DateFormatConstant::DATE_RANGE_SEPARATOR . "{$dateTo}";

        $exportType = ExportTypeConstant::normalizeFormat($format);

        // Set export type and period label in genericData
        $data->exportType = $exportType;
        $data->periodLabel = $periodLabel;
        $genericData->syncDataArray();

        // Get title for filename
        $title = match ($reportType) {
            ReportTypeConstant::COLLECTION => 'Collection Report',
            ReportTypeConstant::EXPENSE => 'Expense Report',
            ReportTypeConstant::SUMMARY => 'Summary Report',
            default => 'Report',
        };

        $fileExtension = ExportTypeConstant::getFileExtension($exportType);
        $filename = strtolower(str_replace(' ', '-', $title)) . '-' . $dateFrom . '.' . $fileExtension;

        $filePath = $this->generateReportFile($genericData, $reportType, $exportType, $filename);

        $result = new \stdClass();
        $result->filePath = $filePath;
        $result->title = $title;

        return $result;
    }

    /**
     * Generate report file and save to disk
     *
     * @param GenericData $genericData
     * @param string $reportType
     * @param string $exportType
     * @param string $filename
     * @return string file path
     */
    private function generateReportFile(GenericData $genericData, string $reportType, string $exportType, string $filename): string
    {
        $path = 'reports/' . $filename;
        $fullPath = Storage::path($path);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($exportType === ExportTypeConstant::PDF) {
            return $this->generatePdfFile($genericData, $reportType, $fullPath);
        } else {
            return $this->generateExcelFile($genericData, $reportType, $fullPath, $path);
        }
    }

    /**
     * Generate PDF file and save to disk
     */
    private function generatePdfFile(GenericData $genericData, string $reportType, string $filePath): string
    {
        $viewName = match ($reportType) {
            ReportTypeConstant::COLLECTION => 'reports.collection-report',
            ReportTypeConstant::EXPENSE => 'reports.expense-report',
            ReportTypeConstant::SUMMARY => 'reports.summary-report',
            default => 'reports.collection-report',
        };

        $data = $this->getReportData($genericData, $reportType);
        $html = view($viewName, $data)->render();

        PdfFacade::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->save($filePath);

        return $filePath;
    }

    /**
     * Generate Excel file and save to disk
     */
    private function generateExcelFile(GenericData $genericData, string $reportType, string $filePath, string $storagePath): string
    {
        $data = $this->getReportData($genericData, $reportType);

        $exportClass = match ($reportType) {
            ReportTypeConstant::COLLECTION => CollectionReportSheet::class,
            ReportTypeConstant::EXPENSE => ExpenseReportSheet::class,
            ReportTypeConstant::SUMMARY => SummaryReportSheet::class,
            default => CollectionReportSheet::class,
        };

        $summaryHeaderData = [
            'businessName' => $data['summaryHeaderData']['businessName'] ?? 'Kaizen Gym',
            'title' => $data['summaryHeaderData']['title'] ?? 'Report',
            'summaryRows' => $data['summaryHeaderData']['summaryRows'] ?? [],
            'periodLabel' => $data['periodLabel'] ?? '',
            'generatedAt' => $data['generatedAt'] ?? '',
        ];

        $export = new $exportClass($summaryHeaderData, $data['records']);
        Excel::store($export, $storagePath);

        return $filePath;
    }

    /**
     * Get report data using the new module-based services
     */
    private function getReportData(GenericData $genericData, string $reportType): array
    {
        return match ($reportType) {
            ReportTypeConstant::COLLECTION => $this->getCollectionReportData($genericData),
            ReportTypeConstant::EXPENSE => $this->getExpenseReportData($genericData),
            ReportTypeConstant::SUMMARY => $this->getSummaryReportData($genericData),
            default => throw new \InvalidArgumentException("Unknown report type: {$reportType}"),
        };
    }

    /**
     * Get collection report data
     */
    private function getCollectionReportData(GenericData $genericData): array
    {
        $data = $genericData->getData();
        $collectionData = $this->customerBillRepository->getForExport($genericData->userData->account_id, $data->dateFrom, $data->dateTo);
        $records = $this->exportCollectionService->transformData($collectionData);
        $summaryHeaderData = $this->exportCollectionService->getSummaryHeaderData($collectionData);
        $headers = $this->exportCollectionService->getHeaders();
        $periodLabel = $data->periodLabel ?? $data->dateFrom . DateFormatConstant::DATE_RANGE_SEPARATOR . $data->dateTo;
        $generatedAt = Carbon::now()->toDateTimeString();

        return [
            'summaryHeaderData' => $summaryHeaderData,
            'headers' => $headers,
            'records' => $records,
            'periodLabel' => $periodLabel,
            'generatedAt' => $generatedAt,
        ];
    }

    /**
     * Get expense report data
     */
    private function getExpenseReportData(GenericData $genericData): array
    {
        $data = $genericData->getData();
        $expenseData = $this->expenseRepository->getForExport($genericData->userData->account_id, $data->dateFrom, $data->dateTo);
        $records = $this->exportExpenseService->transformData($expenseData);
        $summaryHeaderData = $this->exportExpenseService->getSummaryHeaderData($expenseData);
        $headers = $this->exportExpenseService->getHeaders();
        $periodLabel = $data->periodLabel ?? $data->dateFrom . DateFormatConstant::DATE_RANGE_SEPARATOR . $data->dateTo;
        $generatedAt = Carbon::now()->toDateTimeString();

        return [
            'summaryHeaderData' => $summaryHeaderData,
            'headers' => $headers,
            'records' => $records,
            'periodLabel' => $periodLabel,
            'generatedAt' => $generatedAt,
        ];
    }

    /**
     * Get summary report data
     */
    private function getSummaryReportData(GenericData $genericData): array
    {
        $data = $genericData->getData();
        $billData = $this->customerBillRepository->getForExport($genericData->userData->account_id, $data->dateFrom, $data->dateTo);
        $expenseData = $this->expenseRepository->getForExport($genericData->userData->account_id, $data->dateFrom, $data->dateTo);
        $records = $this->exportSummaryService->transformData($expenseData);
        $summaryHeaderData = $this->exportSummaryService->getSummaryHeaderData($billData, $expenseData);
        $headers = $this->exportSummaryService->getHeaders();
        $periodLabel = $data->periodLabel ?? $data->dateFrom . DateFormatConstant::DATE_RANGE_SEPARATOR . $data->dateTo;
        $generatedAt = Carbon::now()->toDateTimeString();

        return [
            'summaryHeaderData' => $summaryHeaderData,
            'headers' => $headers,
            'records' => $records,
            'periodLabel' => $periodLabel,
            'generatedAt' => $generatedAt,
        ];
    }
}
