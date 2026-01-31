<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email report (PDF or Excel) when export exceeds 200 rows.
 */
class ReportEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $reportTitle,
        public string $filePath,
        public string $format
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your report: ' . $this->reportTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.report-email',
            with: [
                'reportTitle' => $this->reportTitle,
                'format' => $this->format,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $filename = basename($this->filePath);
        return [
            Attachment::fromPath($this->filePath)->as($filename),
        ];
    }
}
