<?php

namespace App\Mail;

use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use Carbon\Carbon;
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
        // APP_URL is the single source of truth for the public app URL (see 9052972);
        // read it via config() rather than env() so it survives `config:cache`.
        $frontendUrl = rtrim((string) config('app.url'), '/');

        // membership_end_date is cast to `date` (midnight) while now() carries wall-clock
        // time, so the difference is never whole - and Carbon 3 (Laravel 12) returns that
        // fraction as a float instead of truncating like Carbon 2 did. Anchoring both
        // sides to midnight and rounding up gives a clean integer. `false` keeps the sign
        // so an already-expired membership reads negative instead of flipping positive.
        $daysRemaining = (int) ceil(
            Carbon::today()->diffInDays($this->membership->membership_end_date, false)
        );

        return new Content(
            markdown: 'emails.membership-expiring',
            with: [
                'logoUrl' => $frontendUrl . '/img/gymhubph.png',
                'customerName' => $this->customer->first_name . ' ' . $this->customer->last_name,
                'membershipPlan' => $this->membership->membershipPlan->plan_name ?? 'N/A',
                'expirationDate' => $this->membership->membership_end_date->format('F d, Y'),
                'daysRemaining' => $daysRemaining,
                'daysRemainingLabel' => $this->formatDaysRemaining($daysRemaining),
            ],
        );
    }

    /**
     * Human-readable "days remaining" so the template never renders "1 days" or a
     * bare "0" for a membership that lapses today.
     *
     * @param int $days
     *
     * @return string
     */
    private function formatDaysRemaining(int $days): string
    {
        if ($days < 0) {
            $overdue = abs($days);
            return $overdue . ' ' . ($overdue === 1 ? 'day' : 'days') . ' overdue';
        }

        if ($days === 0) {
            return 'Expires today';
        }

        return $days . ' ' . ($days === 1 ? 'day' : 'days');
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
