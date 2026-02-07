<?php

namespace App\Http\Controllers\Account;

use App\Constants\ExportTypeConstant;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CheckExportSizeRequest;
use App\Http\Requests\Core\EmailReportRequest;
use App\Mail\ReportEmailMail;
use App\Services\Account\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService,
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
        $genericData = $request->getGenericDataWithValidated();
        $result = $this->reportService->checkExportSize($genericData);

        return ApiResponse::success($result);
    }

    /**
     * Generate report (PDF or Excel) and send via email when export exceeds 200 rows.
     *
     * @param EmailReportRequest $request reportType, format (pdf|excel), dateRange, dateFrom, dateTo
     * @return JsonResponse
     */
    public function emailReport(EmailReportRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->email) {
            return ApiResponse::error('User email not found.', 400);
        }

        $genericData = $request->getGenericDataWithValidated();
        $result = $this->reportService->generateEmailReportFile($genericData);

        $data = $genericData->getData();
        $format = $data->format;
        Mail::to($user->email)->send(new ReportEmailMail($result->title, $result->filePath, $format));

        if (file_exists($result->filePath)) {
            @unlink($result->filePath);
        }

        return ApiResponse::success(
            ['requested' => true],
            'Report has been sent to your email.',
            202
        );
    }

}
