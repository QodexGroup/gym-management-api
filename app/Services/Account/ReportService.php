<?php

namespace App\Services\Account;

use App\Constants\DateFormatConstant;
use App\Constants\ExportTypeConstant;
use App\Constants\ReportConstant;
use App\Constants\ReportTypeConstant;
use App\Helpers\GenericData;
use App\Exports\CollectionReport\CollectionReportSheet;
use App\Exports\ExpenseReport\ExpenseReportSheet;
use App\Exports\RevenueReport\RevenueReportSheet;
use App\Exports\SummaryReport\SummaryReportSheet;
use App\Modules\CollectionReport\Exporters\ExportCollectionService;
use App\Modules\ExpenseReport\Exporters\ExportExpenseService;
use App\Modules\RevenueReport\Exporters\ExportRevenueService;
use App\Modules\SummaryReport\Exporters\ExportSummaryService;
use App\Repositories\Common\ExpenseRepository;
use App\Repositories\Core\CustomerBillRepository;
use App\Repositories\Core\CustomerPaymentRepository;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ReportService
{

    private CustomerPaymentRepository $customerPaymentRepository;
    private CustomerBillRepository $customerBillRepository;
    private ExpenseRepository $expenseRepository;
    private ExportCollectionService $exportCollectionService;
    private ExportExpenseService $exportExpenseService;
    private ExportSummaryService $exportSummaryService;
    private ExportRevenueService $exportRevenueService;

    public function __construct(
        CustomerPaymentRepository $customerPaymentRepository,
        CustomerBillRepository $customerBillRepository,
        ExpenseRepository $expenseRepository,
        ExportCollectionService $exportCollectionService,
        ExportExpenseService $exportExpenseService,
        ExportSummaryService $exportSummaryService,
        ExportRevenueService $exportRevenueService,
    ) {
        $this->customerPaymentRepository = $customerPaymentRepository;
        $this->customerBillRepository = $customerBillRepository;
        $this->expenseRepository = $expenseRepository;
        $this->exportCollectionService = $exportCollectionService;
        $this->exportExpenseService = $exportExpenseService;
        $this->exportSummaryService = $exportSummaryService;
        $this->exportRevenueService = $exportRevenueService;
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
            case ReportTypeConstant::REVENUE:
                $rowCount = $this->customerBillRepository->countNonVoidedByAccountAndDateRange($accountId, $startDate, $endDate);
                break;
            case ReportTypeConstant::MY_COLLECTION:
                $rowCount = $this->customerPaymentRepository->countCoachPtPaymentsForDateRange($genericData);
                break;
            case ReportTypeConstant::MY_REVENUE:
                $rowCount = $this->customerBillRepository->countCoachPtBillsForDateRange($genericData);
                break;
            default:
                $rowCount = 0;
        }

        return [
            'tooLarge' => $rowCount > ReportConstant::MAX_EXPORT_ROWS,
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
            case ReportTypeConstant::REVENUE:
                return $this->getRevenueReportData($genericData);
            case ReportTypeConstant::MY_COLLECTION:
                return $this->getMyCollectionReportData($genericData);
            case ReportTypeConstant::MY_REVENUE:
                return $this->getMyRevenueReportData($genericData);
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
            case ReportTypeConstant::REVENUE:
                return 'Revenue Report';
            case ReportTypeConstant::MY_COLLECTION:
                return 'My Collection Report';
            case ReportTypeConstant::MY_REVENUE:
                return 'My Revenue Report';
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
            case ReportTypeConstant::REVENUE:
                return 'reports.revenue-report';
            case ReportTypeConstant::MY_COLLECTION:
                return 'reports.collection-report';
            case ReportTypeConstant::MY_REVENUE:
                return 'reports.revenue-report';
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
            case ReportTypeConstant::REVENUE:
                return RevenueReportSheet::class;
            case ReportTypeConstant::MY_COLLECTION:
                return CollectionReportSheet::class;
            case ReportTypeConstant::MY_REVENUE:
                return RevenueReportSheet::class;
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
     * @return array{recentTransactions: array, totalCollectedFromPayments: float, totalRevenueBilled: float, todayCollection: float, todayRevenue: float, reportTooLarge: bool, totalRows: int}
     */
    public function getCollectionDataForApi(int $accountId, string $dateFrom, string $dateTo): array
    {
        $totalRows = $this->customerPaymentRepository->countByAccountAndDateRange($accountId, $dateFrom, $dateTo);
        $reportTooLarge = $totalRows > ReportConstant::MAX_EXPORT_ROWS;
        $payments = $this->customerPaymentRepository->getForExport($accountId, $dateFrom, $dateTo, ReportConstant::MAX_EXPORT_ROWS);
        // Collection (cash basis): payments actually received.
        $totalCollectedFromPayments = $this->customerPaymentRepository->sumByAccountAndDateRange($accountId, $dateFrom, $dateTo);
        $todayCollection = $this->customerPaymentRepository->getTodayRevenueByAccount($accountId);
        // Revenue (accrual basis): amount billed from non-voided bills, whether paid or not.
        $totalRevenueBilled = $this->customerBillRepository->sumBilledRevenueByDateRange($accountId, $dateFrom, $dateTo);
        $todayRevenue = $this->customerBillRepository->sumBilledRevenueForToday($accountId);

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
            'totalRevenueBilled' => $totalRevenueBilled,
            'todayCollection' => $todayCollection,
            'todayRevenue' => $todayRevenue,
            'reportTooLarge' => $reportTooLarge,
            'totalRows' => $totalRows,
        ];
    }

    /**
     * Get revenue report data for API (bill-based). Used by frontend Revenue report page.
     *
     * @param int $accountId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return array{recentBills: array, totalRevenue: float, totalCollected: float, totalOutstanding: float, todayRevenue: float, reportTooLarge: bool, totalRows: int}
     */
    public function getRevenueDataForApi(int $accountId, string $dateFrom, string $dateTo): array
    {
        $totalRows = $this->customerBillRepository->countNonVoidedByAccountAndDateRange($accountId, $dateFrom, $dateTo);
        $reportTooLarge = $totalRows > ReportConstant::MAX_EXPORT_ROWS;
        $bills = $this->customerBillRepository->getForRevenueExport($accountId, $dateFrom, $dateTo, ReportConstant::MAX_EXPORT_ROWS);

        // Revenue (accrual): amount billed. Collected: amount already paid against those bills.
        $totalRevenue = $this->customerBillRepository->sumBilledRevenueByDateRange($accountId, $dateFrom, $dateTo);
        $totalCollected = $this->customerBillRepository->sumPaidOnNonVoidedByDateRange($accountId, $dateFrom, $dateTo);
        $todayRevenue = $this->customerBillRepository->sumBilledRevenueForToday($accountId);

        $recentBills = [];
        foreach ($bills as $bill) {
            $customerName = $bill->relationLoaded('customer') && $bill->customer
                ? trim(($bill->customer->first_name ?? '') . ' ' . ($bill->customer->last_name ?? ''))
                : 'N/A';
            $gross = (float) $bill->gross_amount;
            $net = (float) $bill->net_amount;
            $paid = (float) $bill->paid_amount;
            $recentBills[] = [
                'id' => $bill->id,
                'billDate' => Carbon::parse($bill->bill_date)->format('Y-m-d'),
                'customerName' => $customerName ?: 'N/A',
                'billType' => $bill->bill_type ?? 'N/A',
                'grossAmount' => $gross,
                'discountAmount' => round($gross - $net, 2),
                'netAmount' => $net,
                'paidAmount' => $paid,
                'balance' => round($net - $paid, 2),
                'billStatus' => $bill->bill_status ?? 'N/A',
            ];
        }

        return [
            'recentBills' => $recentBills,
            'totalRevenue' => $totalRevenue,
            'totalCollected' => $totalCollected,
            'totalOutstanding' => round($totalRevenue - $totalCollected, 2),
            'todayRevenue' => $todayRevenue,
            'reportTooLarge' => $reportTooLarge,
            'totalRows' => $totalRows,
        ];
    }

    /**
     * Get My Collection report data (coach payment-based) for file export.
     */
    private function getMyCollectionReportData(GenericData $genericData): array
    {
        $data = $genericData->getData();
        $paymentData = $this->customerPaymentRepository->getCoachPtPaymentsForDateRange($genericData, null);
        $records = $this->exportCollectionService->transformData($paymentData);
        $summaryHeaderData = $this->exportCollectionService->getSummaryHeaderData($paymentData);
        $summaryHeaderData['title'] = 'My Collection Report';
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
     * Get My Revenue report data (coach bill-based) for file export.
     */
    private function getMyRevenueReportData(GenericData $genericData): array
    {
        $data = $genericData->getData();
        $billData = $this->customerBillRepository->getCoachPtBillsForDateRange($genericData, null);
        $records = $this->exportRevenueService->transformData($billData);
        $summaryHeaderData = $this->exportRevenueService->getSummaryHeaderData($billData);
        $summaryHeaderData['title'] = 'My Revenue Report';
        $headers = $this->exportRevenueService->getHeaders();
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
     * Get revenue report data (bill-based) for file export.
     */
    private function getRevenueReportData(GenericData $genericData): array
    {
        $data = $genericData->getData();
        $billData = $this->customerBillRepository->getForRevenueExport($genericData->userData->account_id, $data->startDate, $data->endDate, null);
        $records = $this->exportRevenueService->transformData($billData);
        $summaryHeaderData = $this->exportRevenueService->getSummaryHeaderData($billData);
        $headers = $this->exportRevenueService->getHeaders();
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
