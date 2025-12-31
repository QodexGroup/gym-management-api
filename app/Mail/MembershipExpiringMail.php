<?php

namespace App\Mail;

use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MembershipExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    public $customer;
    public $membership;

    /**
     * Create a new message instance.
     */
    public function __construct(Customer $customer, CustomerMembership $membership)
    {
        $this->customer = $customer;
        $this->membership = $membership;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Gym Membership is Expiring Soon',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.membership-expiring',
            with: [
                'customerName' => $this->customer->first_name . ' ' . $this->customer->last_name,
                'membershipPlan' => $this->membership->membershipPlan->name ?? 'N/A',
                'expirationDate' => $this->membership->membership_end_date->format('F d, Y'),
                'daysRemaining' => now()->diffInDays($this->membership->membership_end_date),
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
