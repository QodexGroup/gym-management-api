<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetUrl;

    public function __construct(string $resetUrl)
    {
        $this->resetUrl = $resetUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your Password - GymHubPH',
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'https://gymhubtech-67e6f.web.app'), '/');

        return new Content(
            markdown: 'emails.forgot-password',
            with: [
                'resetUrl' => $this->resetUrl,
                'logoUrl' => $frontendUrl . '/img/gymhubph.png',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
