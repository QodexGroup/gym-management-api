<?php

namespace App\Mail;

use App\Models\Core\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $customer;

    /**
     * Create a new message instance.
     */
    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Our Gym!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $membership = $this->customer->memberships()->latest()->first();
        
        return new Content(
            markdown: 'emails.customer-registration',
            with: [
                'customerName' => $this->customer->first_name . ' ' . $this->customer->last_name,
                'membershipPlan' => $membership?->membershipPlan?->name ?? 'No active membership',
                'membershipStartDate' => $membership?->membership_start_date?->format('F d, Y') ?? 'N/A',
                'membershipEndDate' => $membership?->membership_end_date?->format('F d, Y') ?? 'N/A',
                'hasMembership' => !is_null($membership),
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
