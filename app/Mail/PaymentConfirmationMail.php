<?php

namespace App\Mail;

use App\Models\Core\Customer;
use App\Models\Core\CustomerPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $customer;
    public $payment;

    /**
     * Create a new message instance.
     */
    public function __construct(Customer $customer, CustomerPayment $payment)
    {
        $this->customer = $customer;
        $this->payment = $payment;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Confirmation - Thank You!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment-confirmation',
            with: [
                'customerName' => $this->customer->first_name . ' ' . $this->customer->last_name,
                'amount' => number_format($this->payment->amount, 2),
                'paymentMethod' => ucfirst($this->payment->payment_method),
                'paymentDate' => $this->payment->payment_date->format('F d, Y'),
                'referenceNumber' => $this->payment->reference_number ?? 'N/A',
                'billAmount' => number_format($this->payment->bill->net_amount ?? 0, 2),
                'remainingBalance' => number_format(
                    ($this->payment->bill->net_amount ?? 0) - ($this->payment->bill->paid_amount ?? 0),
                    2
                ),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
