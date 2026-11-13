<?php

namespace App\Jobs;

use App\Constant\NotificationConstant;
use App\Helpers\GenericData;
use App\Mail\ReportEmailMail;
use App\Models\User;
use App\Repositories\Core\NotificationRepository;
use App\Services\Account\ReportService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendReportEmail extends BaseEmailJob
{
    /**
     * Longer than the 30 seconds every other email job gets, and the one place
     * that rule is broken on purpose.
     *
     * Those jobs render a short Blade template; this one renders a report the
     * caller was already told was too large to download, so the work before
     * the send is the slow part. 75 stays under the queue's retry_after of 90
     * (config/queue.php), which is what matters: a job still running when that
     * window expires is handed to a second worker and the member receives the
     * report twice.
     *
     * Raising this means raising DB_QUEUE_RETRY_AFTER with it.
     *
     * @var int
     */
    public int $timeout = 75;

    /**
     * @param int $userId the user who asked for the report and will receive it
     * @param array{reportType: string, format: string, dateRange: string|null, startDate: string, endDate: string} $reportParams
     *        already validated by EmailReportRequest
     */
    public function __construct(
        public int $userId,
        public array $reportParams
    ) {}

    /**
     * Render the report and email it, removing the file in every outcome.
     *
     * Generation happens here rather than in the request so the file is
     * written and deleted by the same process; nothing else ever sees it.
     *
     * @return void
     */
    protected function execute(): void
    {
        $user = User::find($this->userId);
        if (!$user || !$user->email) {
            Log::warning('Report email skipped; user no longer has an address', [
                'user_id' => $this->userId,
            ]);

            return;
        }

        $this->recipient = $user->email;

        $report = app(ReportService::class)->generateEmailReportFile($this->genericData($user));

        try {
            Mail::to($user->email)->send(
                new ReportEmailMail($report->title, $report->filePath, $this->reportParams['format'])
            );

            Log::info('Report email sent', [
                'user_id' => $user->id,
                'report_type' => $this->reportParams['reportType'],
                'format' => $this->reportParams['format'],
            ]);
        } finally {
            $this->deleteFile($report->filePath);
        }
    }

    /**
     * The report is lost. Clear any file the final attempt left behind and
     * tell the user in-app, since the email that would have told them is the
     * thing that failed.
     *
     * @param \Throwable|null $th the cause, or null when the queue gave no exception
     * @return void
     */
    protected function onGaveUp(?\Throwable $th): void
    {
        $this->deleteLeftoverFiles();

        $user = User::find($this->userId);
        if (!$user) {
            return;
        }

        try {
            app(NotificationRepository::class)->create([
                'account_id' => $user->account_id,
                'user_id' => $user->id,
                'type' => NotificationConstant::TYPE_REPORT_EMAIL_FAILED,
                'title' => 'Report could not be emailed',
                'message' => 'Your report could not be emailed. Please try again, or export a smaller date range.',
                'data' => [
                    'report_type' => $this->reportParams['reportType'] ?? null,
                    'format' => $this->reportParams['format'] ?? null,
                    'start_date' => $this->reportParams['startDate'] ?? null,
                    'end_date' => $this->reportParams['endDate'] ?? null,
                ],
            ]);
        } catch (\Throwable $notifyFailure) {
            // This runs on the failure path, where the database may be what
            // broke. A throw here would replace the real failure report.
            Log::error('Could not create the report email failure notification', [
                'user_id' => $this->userId,
                'error' => $notifyFailure->getMessage(),
            ]);
        }
    }

    /**
     * The address this report was for, looked up again because failed() runs
     * on a job rebuilt from the queue payload.
     *
     * @return string|null the address, or null when it no longer resolves
     */
    protected function resolveRecipient(): ?string
    {
        return User::find($this->userId)?->email;
    }

    /**
     * Rebuild the shape the report generator expects from the primitives in
     * the payload. Only userData and the validated parameters are read on this
     * path; filters, sorts and relations play no part in a report export.
     *
     * @param User $user
     * @return GenericData
     */
    private function genericData(User $user): GenericData
    {
        $genericData = new GenericData();
        $genericData->userData = $user;
        $genericData->data = $this->reportParams;

        return $genericData;
    }

    /**
     * Remove a generated report, ignoring a file that is already gone.
     *
     * @param string $filePath
     * @return void
     */
    private function deleteFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    /**
     * Clear anything an earlier attempt left on disk.
     *
     * execute() deletes in a finally, so a file survives only when the worker
     * was killed outright. The name is deterministic for a given report and
     * date, so it can be rebuilt here without being carried in the payload.
     *
     * @return void
     */
    private function deleteLeftoverFiles(): void
    {
        try {
            $user = User::find($this->userId);
            if (!$user) {
                return;
            }

            $this->deleteFile(
                app(ReportService::class)->emailReportFilePath($this->genericData($user))
            );
        } catch (\Throwable $th) {
            Log::error('Could not clear the leftover report file', [
                'user_id' => $this->userId,
                'error' => $th->getMessage(),
            ]);
        }
    }
}
