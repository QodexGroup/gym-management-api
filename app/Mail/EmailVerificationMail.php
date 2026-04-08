<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $verificationUrl;

    public function __construct(string $verificationUrl)
    {
        $this->verificationUrl = $verificationUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Email Address - GymHubPH',
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'https://gymhubtech-67e6f.web.app'), '/');

        return new Content(
            markdown: 'emails.email-verification',
            with: [
                'verificationUrl' => $this->verificationUrl,
                'logoUrl' => $frontendUrl . '/img/gymhubph.png',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
