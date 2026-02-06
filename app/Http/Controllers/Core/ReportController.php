<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CheckExportSizeRequest;
use App\Http\Requests\Core\CollectionReportRequest;
use App\Http\Requests\Core\ExpenseReportRequest;
use App\Http\Requests\Core\SummaryReportRequest;
use App\Mail\ReportEmailMail;
use App\Modules\CollectionReport\CollectionReportExportService;
use App\Modules\ExpenseReport\ExpenseReportExportService;
use App\Modules\SummaryReport\SummaryReportExportService;
use App\Services\Core\Export\ExportExcel;
use App\Services\Core\Export\ExportPdf;
use App\Services\Core\Export\ReportExportDataBuilder;
use App\Services\Core\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService,
        private ReportExportDataBuilder $exportDataBuilder,
        private ExportPdf $exportPdf,
        private ExportExcel $exportExcel,
        private CollectionReportExportService $collectionReportExportService,
        private ExpenseReportExportService $expenseReportExportService,
        private SummaryReportExportService $summaryReportExportService,
    ) {
    }

    /**
     * Check if report data for the given range is too large for direct export.
     * Used before Export PDF/Excel: if tooLarge, frontend shows Swal and sends via email instead.
     *
     * @param CheckExportSizeRequest $request reportType, dateFrom, dateTo (validated)
     * @return JsonResponse { tooLarge: bool, rowCount: int }
     */
    public function checkExportSize(CheckExportSizeRequest $request): JsonResponse
    {
        $genericData = $request->getGenericData();
        $accountId = $genericData->userData->account_id;
        $validated = $request->validated();

        $result = $this->reportService->checkExportSize(
            $accountId,
            $validated['reportType'],
            $validated['dateFrom'],
            $validated['dateTo']
        );

        return ApiResponse::success($result);
    }

    /**
     * Generate report (PDF or Excel) and send via email when export exceeds 200 rows.
     *
     * @param Request $request reportType, format (pdf|excel), dateRange, dateFrom, dateTo
     * @return JsonResponse
     */
    public function emailReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reportType' => ['required', 'string', 'in:collection,expense,summary'],
            'format' => ['nullable', 'string', 'in:pdf,excel'],
            'dateRange' => ['nullable', 'string'],
            'dateFrom' => ['required', 'string', 'date'],
            'dateTo' => ['required', 'string', 'date'],
        ]);

        $user = $request->user();
        if (! $user || ! $user->email) {
            return ApiResponse::error('User email not found.', 400);
        }

        $accountId = $user->account_id;
        $reportType = $validated['reportType'];
        $format = $validated['format'] ?? 'pdf';
        $dateFrom = $validated['dateFrom'];
        $dateTo = $validated['dateTo'];
        $periodLabel = $validated['dateRange'] ?? "{$dateFrom} – {$dateTo}";

        $payload = $this->exportDataBuilder->buildPayload($reportType, $accountId, $dateFrom, $dateTo, $periodLabel);
        $title = $payload['title'];

        $filename = strtolower(str_replace(' ', '-', $title)) . '-' . $dateFrom . '.' . ($format === 'pdf' ? 'pdf' : 'xlsx');
        $filePath = $format === 'pdf'
            ? $this->exportPdf->generate($payload, $filename)
            : $this->exportExcel->generate($payload, $filename);

        Mail::to($user->email)->send(new ReportEmailMail($title, $filePath, $format));

        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        return ApiResponse::success(
            ['requested' => true],
            'Report has been sent to your email.',
            202
        );
    }

    /**
     * Get collection records or export if exportType is provided.
     *
     * @param CollectionReportRequest $request
     * @return mixed
     */
    public function getCollectionRecords(CollectionReportRequest $request)
    {
        $collectionDto = $request->toCollectionReportDto();
        if (empty($collectionDto->getExportType())) {
            // Return JSON data (you can add a service method for this if needed)
            return ApiResponse::success(['message' => 'Collection records endpoint - add data retrieval logic here']);
        }
        return $this->collectionReportExportService->export($collectionDto);
    }

    /**
     * Get expense records or export if exportType is provided.
     *
     * @param ExpenseReportRequest $request
     * @return mixed
     */
    public function getExpenseRecords(ExpenseReportRequest $request)
    {
        $expenseDto = $request->toExpenseReportDto();
        if (empty($expenseDto->getExportType())) {
            // Return JSON data (you can add a service method for this if needed)
            return ApiResponse::success(['message' => 'Expense records endpoint - add data retrieval logic here']);
        }
        return $this->expenseReportExportService->export($expenseDto);
    }

    /**
     * Get summary records or export if exportType is provided.
     *
     * @param SummaryReportRequest $request
     * @return mixed
     */
    public function getSummaryRecords(SummaryReportRequest $request)
    {
        $summaryDto = $request->toSummaryReportDto();
        if (empty($summaryDto->getExportType())) {
            // Return JSON data (you can add a service method for this if needed)
            return ApiResponse::success(['message' => 'Summary records endpoint - add data retrieval logic here']);
        }
        return $this->summaryReportExportService->export($summaryDto);
    }
}
