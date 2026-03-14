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
use App\Repositories\Core\CustomerPaymentRepository;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ReportService
{
    private const MAX_EXPORT_ROWS = 200;

    private CustomerPaymentRepository $customerPaymentRepository;
    private ExpenseRepository $expenseRepository;
    private ExportCollectionService $exportCollectionService;
    private ExportExpenseService $exportExpenseService;
    private ExportSummaryService $exportSummaryService;

    public function __construct(
        CustomerPaymentRepository $customerPaymentRepository,
        ExpenseRepository $expenseRepository,
        ExportCollectionService $exportCollectionService,
        ExportExpenseService $exportExpenseService,
        ExportSummaryService $exportSummaryService,
    ) {
        $this->customerPaymentRepository = $customerPaymentRepository;
        $this->expenseRepository = $expenseRepository;
        $this->exportCollectionService = $exportCollectionService;
        $this->exportExpenseService = $exportExpenseService;
        $this->exportSummaryService = $exportSummaryService;
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
        $startDate = $data->startDate;
        $endDate = $data->endDate;

        switch ($reportType) {
            case ReportTypeConstant::COLLECTION:
                $rowCount = $this->customerPaymentRepository->countByAccountAndDateRange($accountId, $startDate, $endDate);
                break;
            case ReportTypeConstant::EXPENSE:
            case ReportTypeConstant::SUMMARY:
                $rowCount = $this->expenseRepository->countByAccountAndDateRange($accountId, $startDate, $endDate);
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
        $startDate = $data->startDate;
        $endDate = $data->endDate;
        $format = $data->format ?? ExportTypeConstant::PDF;
        $periodLabel = $data->dateRange ?? "{$startDate}" . DateFormatConstant::DATE_RANGE_SEPARATOR . "{$endDate}";

        $exportType = ExportTypeConstant::normalizeFormat($format);

        // Set export type and period label in genericData
        $data->exportType = $exportType;
        $data->periodLabel = $periodLabel;
        $genericData->syncDataArray();

        // Get title for filename
        $title = $this->getReportTitle($reportType);

        $fileExtension = ExportTypeConstant::getFileExtension($exportType);
        $filename = strtolower(str_replace(' ', '-', $title)) . '-' . $startDate . '.' . $fileExtension;

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
        $viewName = $this->getReportViewName($reportType);

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

        $exportClass = $this->getReportExportClass($reportType);

        $summaryHeaderData = [
            'businessName' => $data['summaryHeaderData']['businessName'] ?? config('app.name'),
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
        switch ($reportType) {
            case ReportTypeConstant::COLLECTION:
                return $this->getCollectionReportData($genericData);
            case ReportTypeConstant::EXPENSE:
                return $this->getExpenseReportData($genericData);
            case ReportTypeConstant::SUMMARY:
                return $this->getSummaryReportData($genericData);
            default:
                throw new \InvalidArgumentException("Unknown report type: {$reportType}");
        }
    }

    private function getReportTitle(string $reportType): string
    {
        switch ($reportType) {
            case ReportTypeConstant::COLLECTION:
                return 'Collection Report';
            case ReportTypeConstant::EXPENSE:
                return 'Expense Report';
            case ReportTypeConstant::SUMMARY:
                return 'Summary Report';
            default:
                return 'Report';
        }
    }

    private function getReportViewName(string $reportType): string
    {
        switch ($reportType) {
            case ReportTypeConstant::COLLECTION:
                return 'reports.collection-report';
            case ReportTypeConstant::EXPENSE:
                return 'reports.expense-report';
            case ReportTypeConstant::SUMMARY:
                return 'reports.summary-report';
            default:
                return 'reports.collection-report';
        }
    }

    /**
     * @return class-string
     */
    private function getReportExportClass(string $reportType): string
    {
        switch ($reportType) {
            case ReportTypeConstant::COLLECTION:
                return CollectionReportSheet::class;
            case ReportTypeConstant::EXPENSE:
                return ExpenseReportSheet::class;
            case ReportTypeConstant::SUMMARY:
                return SummaryReportSheet::class;
            default:
                return CollectionReportSheet::class;
        }
    }

    /**
     * Get collection report data for API (payment-based). Used by frontend Collection/Summary report pages.
     *
     * @param int $accountId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return array{recentTransactions: array, totalCollectedFromPayments: float, todayRevenue: float, reportTooLarge: bool, totalRows: int}
     */
    public function getCollectionDataForApi(int $accountId, string $dateFrom, string $dateTo): array
    {
        $totalRows = $this->customerPaymentRepository->countByAccountAndDateRange($accountId, $dateFrom, $dateTo);
        $reportTooLarge = $totalRows > self::MAX_EXPORT_ROWS;
        $payments = $this->customerPaymentRepository->getForExport($accountId, $dateFrom, $dateTo, self::MAX_EXPORT_ROWS);
        $totalCollectedFromPayments = $this->customerPaymentRepository->sumByAccountAndDateRange($accountId, $dateFrom, $dateTo);
        $todayRevenue = $this->customerPaymentRepository->getTodayRevenueByAccount($accountId);

        $recentTransactions = [];
        foreach ($payments as $payment) {
            $customerName = $payment->relationLoaded('customer') && $payment->customer
                ? trim(($payment->customer->first_name ?? '') . ' ' . ($payment->customer->last_name ?? ''))
                : 'N/A';
            $billType = 'N/A';
            if ($payment->relationLoaded('bill') && $payment->bill) {
                $billType = $payment->bill->bill_type ?? 'N/A';
            }
            $recentTransactions[] = [
                'id' => $payment->id,
                'paymentDate' => Carbon::parse($payment->payment_date)->format('Y-m-d'),
                'customerName' => $customerName,
                'billType' => $billType,
                'amount' => (float) $payment->amount,
                'paymentMethod' => $payment->payment_method ?? null,
            ];
        }

        return [
            'recentTransactions' => $recentTransactions,
            'totalCollectedFromPayments' => $totalCollectedFromPayments,
            'todayRevenue' => $todayRevenue,
            'reportTooLarge' => $reportTooLarge,
            'totalRows' => $totalRows,
        ];
    }

    /**
     * Get collection report data (payment-based)
     */
    private function getCollectionReportData(GenericData $genericData): array
    {
        $data = $genericData->getData();
        $paymentData = $this->customerPaymentRepository->getForExport($genericData->userData->account_id, $data->startDate, $data->endDate, null);
        $records = $this->exportCollectionService->transformData($paymentData);
        $summaryHeaderData = $this->exportCollectionService->getSummaryHeaderData($paymentData);
        $headers = $this->exportCollectionService->getHeaders();
        $periodLabel = $data->periodLabel ?? $data->startDate . DateFormatConstant::DATE_RANGE_SEPARATOR . $data->endDate;
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
        $expenseData = $this->expenseRepository->getForExport($genericData->userData->account_id, $data->startDate, $data->endDate);
        $records = $this->exportExpenseService->transformData($expenseData);
        $summaryHeaderData = $this->exportExpenseService->getSummaryHeaderData($expenseData);
        $headers = $this->exportExpenseService->getHeaders();
        $periodLabel = $data->periodLabel ?? $data->startDate . DateFormatConstant::DATE_RANGE_SEPARATOR . $data->endDate;
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
     * Get summary report data (revenue from payments, expenses unchanged)
     */
    private function getSummaryReportData(GenericData $genericData): array
    {
        $data = $genericData->getData();
        $paymentData = $this->customerPaymentRepository->getForExport($genericData->userData->account_id, $data->startDate, $data->endDate, null);
        $expenseData = $this->expenseRepository->getForExport($genericData->userData->account_id, $data->startDate, $data->endDate);
        $records = $this->exportSummaryService->transformData($expenseData);
        $summaryHeaderData = $this->exportSummaryService->getSummaryHeaderData($paymentData, $expenseData);
        $headers = $this->exportSummaryService->getHeaders();
        $periodLabel = $data->periodLabel ?? $data->startDate . DateFormatConstant::DATE_RANGE_SEPARATOR . $data->endDate;
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
