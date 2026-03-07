<?php

namespace App\Mail;

use App\Models\Account\AccountInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountInvoiceNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public const TYPE_ISSUED = 'issued';
    public const TYPE_DUE_REMINDER = 'due_reminder';
    public const TYPE_OVERDUE = 'overdue';
    public const TYPE_LOCK_NOTICE = 'lock_notice';

    public function __construct(
        public AccountInvoice $invoice,
        public string $type
    ) {
    }

    public function envelope(): Envelope
    {
        $subjects = [
            self::TYPE_ISSUED => 'Your subscription invoice ' . $this->invoice->invoice_number . ' has been issued',
            self::TYPE_DUE_REMINDER => 'Reminder: Invoice ' . $this->invoice->invoice_number . ' is due on the 5th',
            self::TYPE_OVERDUE => 'Overdue: Please pay invoice ' . $this->invoice->invoice_number,
            self::TYPE_LOCK_NOTICE => 'Account locked: Invoice ' . $this->invoice->invoice_number . ' unpaid past 10th',
        ];
        return new Envelope(
            subject: $subjects[$this->type] ?? 'Subscription invoice update',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account-invoice-notification',
            with: [
                'invoice' => $this->invoice,
                'type' => $this->type,
                'accountName' => $this->invoice->account->name ?? 'Account',
            ],
        );
    }
}
