<?php

namespace App\Services\Core;

use App\Constant\NotificationConstant;
use App\Mail\CustomerRegistrationMail;
use App\Mail\MembershipExpiringMail;
use App\Mail\PaymentConfirmationMail;
use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use App\Models\Core\CustomerPayment;
use App\Models\Core\Notification;
use App\Repositories\Core\NotificationRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    private static $emailQueueCounter = 0;

    public function __construct(
        protected NotificationRepository $notificationRepository,
        protected \App\Repositories\Core\NotificationPreferenceRepository $preferenceRepository
    ) {
    }

    /**
     * Get the delay in seconds for the next email in queue.
     * Increments by 10 seconds for each email to respect Mailtrap's rate limits.
     * Mailtrap free tier: 10 seconds per email (rolling window)
     *
     * @return int
     */
    private function getEmailQueueDelay(): int
    {
        return self::$emailQueueCounter++ * 10; // 0s, 10s, 20s, 30s, etc.
    }

    /**
     * Check if membership expiry notifications are enabled.
     *
     * @return bool
     */
    private function shouldSendMembershipExpiryNotification(): bool
    {
        $prefs = $this->preferenceRepository->getByAccountId(1);
        return $prefs === null ? true : ($prefs->membership_expiry_enabled ?? true);
    }

    /**
     * Check if payment alert notifications are enabled.
     *
     * @return bool
     */
    private function shouldSendPaymentAlertNotification(): bool
    {
        $prefs = $this->preferenceRepository->getByAccountId(1);
        return $prefs === null ? true : ($prefs->payment_alerts_enabled ?? true);
    }

    /**
     * Check if new registration notifications are enabled.
     *
     * @return bool
     */
    private function shouldSendNewRegistrationNotification(): bool
    {
        $prefs = $this->preferenceRepository->getByAccountId(1);
        return $prefs === null ? true : ($prefs->new_registrations_enabled ?? true);
    }

    /**
     * Create notification for expiring membership.
     *
     * @param CustomerMembership $membership
     * @return void
     */
    public function createMembershipExpiringNotification(CustomerMembership $membership): void
    {
        // Check if membership expiry notifications are enabled
        if (!$this->shouldSendMembershipExpiryNotification()) {
            Log::info('Membership expiry notifications disabled', [
                'customer_id' => $membership->customer_id,
                'membership_id' => $membership->id
            ]);
            return;
        }

        try {
            $customer = $membership->customer;
            
            // Check if notification already sent in last 24 hours
            if ($this->notificationRepository->notificationExists(
                NotificationConstant::TYPE_MEMBERSHIP_EXPIRING,
                [
                    'customer_id' => $customer->id,
                    'membership_id' => $membership->id
                ],
                24
            )) {
                Log::info('Membership expiring notification already sent', [
                    'customer_id' => $customer->id,
                    'membership_id' => $membership->id
                ]);
                return;
            }

            // Send email to customer
            $this->sendMembershipExpiringEmail($customer, $membership);

            // Create global in-app notification
            $this->notificationRepository->create([
                'user_id' => null, // Global notification
                'type' => NotificationConstant::TYPE_MEMBERSHIP_EXPIRING,
                'title' => 'Membership Expiring Soon',
                'message' => "{$customer->first_name} {$customer->last_name}'s membership expires on {$membership->membership_end_date->format('M d, Y')}",
                'data' => [
                    'customer_id' => $customer->id,
                    'customer_name' => "{$customer->first_name} {$customer->last_name}",
                    'membership_id' => $membership->id,
                    'membership_plan' => $membership->membershipPlan->name ?? 'N/A',
                    'expiration_date' => $membership->membership_end_date->format('Y-m-d'),
                    'days_remaining' => now()->diffInDays($membership->membership_end_date),
                ],
            ]);

            // When user management is implemented, uncomment this to create notifications for admin users
            // $adminUsers = User::where('is_admin', true)->get();
            // foreach ($adminUsers as $admin) {
            //     $this->notificationRepository->create([
            //         'user_id' => $admin->id,
            //         'type' => NotificationConstant::TYPE_MEMBERSHIP_EXPIRING,
            //         'title' => 'Membership Expiring Soon',
            //         'message' => "{$customer->first_name} {$customer->last_name}'s membership expires on {$membership->membership_end_date->format('M d, Y')}",
            //         'data' => [
            //             'customer_id' => $customer->id,
            //             'customer_name' => "{$customer->first_name} {$customer->last_name}",
            //             'membership_id' => $membership->id,
            //             'membership_plan' => $membership->membershipPlan->name ?? 'N/A',
            //             'expiration_date' => $membership->membership_end_date->format('Y-m-d'),
            //             'days_remaining' => now()->diffInDays($membership->membership_end_date),
            //         ],
            //     ]);
            // }

            Log::info('Membership expiring notification created', [
                'customer_id' => $customer->id,
                'membership_id' => $membership->id
            ]);
        } catch (\Throwable $th) {
            Log::error('Error creating membership expiring notification', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
        }
    }

    /**
     * Create notification for payment received.
     *
     * @param CustomerPayment $payment
     * @return void
     */
    public function createPaymentReceivedNotification(CustomerPayment $payment): void
    {
        // Check if payment alert notifications are enabled
        if (!$this->shouldSendPaymentAlertNotification()) {
            Log::info('Payment alert notifications disabled', [
                'payment_id' => $payment->id,
                'customer_id' => $payment->customer_id
            ]);
            return;
        }

        try {
            $customer = $payment->customer;
            $bill = $payment->bill;

            // Send email to customer
            $this->sendPaymentConfirmationEmail($customer, $payment);

            // Create global in-app notification
            $this->notificationRepository->create([
                'user_id' => null, // Global notification
                'type' => NotificationConstant::TYPE_PAYMENT_RECEIVED,
                'title' => 'Payment Received',
                'message' => "Payment of ₱{$payment->amount} received from {$customer->first_name} {$customer->last_name}",
                'data' => [
                    'customer_id' => $customer->id,
                    'customer_name' => "{$customer->first_name} {$customer->last_name}",
                    'payment_id' => $payment->id,
                    'bill_id' => $bill->id,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_date' => $payment->payment_date->format('Y-m-d'),
                    'reference_number' => $payment->reference_number,
                ],
            ]);

            // When user management is implemented, uncomment this to create notifications for admin users
            // $adminUsers = User::where('is_admin', true)->get();
            // foreach ($adminUsers as $admin) {
            //     $this->notificationRepository->create([
            //         'user_id' => $admin->id,
            //         'type' => NotificationConstant::TYPE_PAYMENT_RECEIVED,
            //         'title' => 'Payment Received',
            //         'message' => "Payment of ₱{$payment->amount} received from {$customer->first_name} {$customer->last_name}",
            //         'data' => [
            //             'customer_id' => $customer->id,
            //             'customer_name' => "{$customer->first_name} {$customer->last_name}",
            //             'payment_id' => $payment->id,
            //             'bill_id' => $bill->id,
            //             'amount' => $payment->amount,
            //             'payment_method' => $payment->payment_method,
            //             'payment_date' => $payment->payment_date->format('Y-m-d'),
            //             'reference_number' => $payment->reference_number,
            //         ],
            //     ]);
            // }

            Log::info('Payment received notification created', [
                'customer_id' => $customer->id,
                'payment_id' => $payment->id
            ]);
        } catch (\Throwable $th) {
            Log::error('Error creating payment received notification', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
        }
    }

    /**
     * Create notification for new customer registration.
     *
     * @param Customer $customer
     * @return void
     */
    public function createCustomerRegisteredNotification(Customer $customer): void
    {
        // Check if new registration notifications are enabled
        if (!$this->shouldSendNewRegistrationNotification()) {
            Log::info('New registration notifications disabled', [
                'customer_id' => $customer->id
            ]);
            return;
        }

        try {
            // Send welcome email to customer
            $this->sendCustomerRegistrationEmail($customer);

            // Create global in-app notification
            $membership = $customer->memberships()->latest()->first();
            
            $this->notificationRepository->create([
                'user_id' => null, // Global notification
                'type' => NotificationConstant::TYPE_CUSTOMER_REGISTERED,
                'title' => 'New Customer Registered',
                'message' => "{$customer->first_name} {$customer->last_name} has been registered",
                'data' => [
                    'customer_id' => $customer->id,
                    'customer_name' => "{$customer->first_name} {$customer->last_name}",
                    'membership_plan' => ($membership === null || $membership->membershipPlan === null)
                        ? 'No membership'
                        : ($membership->membershipPlan->name ?? 'No membership'),
                    'registration_date' => $customer->created_at->format('Y-m-d'),
                ],
            ]);

            // When user management is implemented, uncomment this to create notifications for admin users
            // $adminUsers = User::where('is_admin', true)->get();
            // foreach ($adminUsers as $admin) {
            //     $this->notificationRepository->create([
            //         'user_id' => $admin->id,
            //         'type' => NotificationConstant::TYPE_CUSTOMER_REGISTERED,
            //         'title' => 'New Customer Registered',
            //         'message' => "{$customer->first_name} {$customer->last_name} has been registered",
            //         'data' => [
            //             'customer_id' => $customer->id,
            //             'customer_name' => "{$customer->first_name} {$customer->last_name}",
            //             'membership_plan' => $membership?->membershipPlan?->name ?? 'No membership',
            //             'registration_date' => $customer->created_at->format('Y-m-d'),
            //         ],
            //     ]);
            // }

            Log::info('Customer registered notification created', [
                'customer_id' => $customer->id
            ]);
        } catch (\Throwable $th) {
            Log::error('Error creating customer registered notification', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
        }
    }

    /**
     * Send membership expiring email to customer.
     *
     * @param Customer $customer
     * @param CustomerMembership $membership
     * @return void
     */
    public function sendMembershipExpiringEmail(Customer $customer, CustomerMembership $membership): void
    {
        if (!$customer->email) {
            Log::warning('Customer has no email address', ['customer_id' => $customer->id]);
            return;
        }

        try {
            // Dispatch job to queue with 1-second delay to respect Mailtrap's rate limit
            // Each subsequent job will be delayed by 1 second from the previous one
            $delay = now()->addSeconds($this->getEmailQueueDelay());
            \App\Jobs\SendMembershipExpiringEmail::dispatch($customer->id, $membership->id)->delay($delay);
            
            Log::info('Membership expiring email queued', [
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'delay_seconds' => $delay->diffInSeconds(now())
            ]);
        } catch (\Throwable $th) {
            Log::error('Error queuing membership expiring email', [
                'error' => $th->getMessage(),
                'customer_id' => $customer->id
            ]);
        }
    }

    /**
     * Send payment confirmation email to customer.
     *
     * @param Customer $customer
     * @param CustomerPayment $payment
     * @return void
     */
    public function sendPaymentConfirmationEmail(Customer $customer, CustomerPayment $payment): void
    {
        if (!$customer->email) {
            Log::warning('Customer has no email address', ['customer_id' => $customer->id]);
            return;
        }

        try {
            // Dispatch job to queue for async processing with rate limiting
            \App\Jobs\SendPaymentConfirmationEmail::dispatch($customer->id, $payment->id);
            
            Log::info('Payment confirmation email queued', [
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'payment_id' => $payment->id
            ]);
        } catch (\Throwable $th) {
            Log::error('Error queuing payment confirmation email', [
                'error' => $th->getMessage(),
                'customer_id' => $customer->id
            ]);
        }
    }

    /**
     * Send customer registration email.
     *
     * @param Customer $customer
     * @return void
     */
    public function sendCustomerRegistrationEmail(Customer $customer): void
    {
        if (!$customer->email) {
            Log::warning('Customer has no email address', ['customer_id' => $customer->id]);
            return;
        }

        try {
            // Dispatch job to queue for async processing with rate limiting
            \App\Jobs\SendCustomerRegistrationEmail::dispatch($customer->id);
            
            Log::info('Customer registration email queued', [
                'customer_id' => $customer->id,
                'email' => $customer->email
            ]);
        } catch (\Throwable $th) {
            Log::error('Error queuing customer registration email', [
                'error' => $th->getMessage(),
                'customer_id' => $customer->id
            ]);
        }
    }

    /**
     * Get paginated notifications (global).
     *
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getNotifications(int $page = 1, int $limit = 20): array
    {
        $offset = (int) (($page - 1) * $limit);
        $limit = (int) max(1, $limit);

        $baseQuery = Notification::where('account_id', 1)
            ->global()
            ->orderBy('created_at', 'desc');

        $total = (clone $baseQuery)->count();
        $lastPage = $total > 0 ? (int) ceil($total / $limit) : 1;
        $notifications = (clone $baseQuery)->offset($offset)->limit($limit)->get();

        return [
            'data' => $notifications,
            'pagination' => [
                'current_page' => (int) $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * Get unread notification count (global).
     *
     * @return int
     */
    public function getUnreadCount(): int
    {
        return $this->notificationRepository->getUnreadCountGlobal();
        
        // When user management is implemented, uncomment this for user-specific count
        // return $this->notificationRepository->getUnreadCountByUserId($userId);
    }

    /**
     * Mark notification as read.
     *
     * @param int $notificationId
     * @return Notification
     */
    public function markAsRead(int $notificationId): Notification
    {
        return $this->notificationRepository->markAsRead($notificationId);
    }

    /**
     * Mark all notifications as read (global).
     *
     * @return int
     */
    public function markAllAsRead(): int
    {
        return $this->notificationRepository->markAllAsReadGlobal();
        
        // When user management is implemented, uncomment this for user-specific mark all
        // return $this->notificationRepository->markAllAsRead($userId);
    }
}
