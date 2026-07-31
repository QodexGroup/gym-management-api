<?php

namespace App\Services\Core;

use App\Constant\NotificationConstant;
use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use App\Models\Core\CustomerPayment;
use App\Models\Core\Notification;
use App\Repositories\Core\NotificationEmailLogRepository;
use App\Repositories\Core\NotificationRepository;
use App\Services\Account\AccountSystemSettingService;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    private static $emailQueueCounter = 0;

    /**
     * Dedup window (hours) for customer expiry-reminder emails. Slightly under
     * 24 so the daily 9:00 AM scheduler never lands inside yesterday's window
     * due to seconds-level timing drift, while manual re-runs the same day
     * are still suppressed.
     */
    private const EMAIL_DEDUP_HOURS = 23;

    /**
     * Per-request cache of account settings so scheduler loops over many
     * memberships of the same account only read settings once.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $settingsCache = [];

    public function __construct(
        protected NotificationRepository $notificationRepository,
        protected NotificationEmailLogRepository $emailLogRepository,
        protected AccountSystemSettingService $systemSettingService
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
     * Read a single account system setting (camelCase key), cached per request.
     *
     * @param int $accountId
     * @param string $camelKey
     * @return mixed
     */
    private function getSetting(int $accountId, string $camelKey): mixed
    {
        if (!array_key_exists($accountId, $this->settingsCache)) {
            $this->settingsCache[$accountId] = $this->systemSettingService->getForAccount($accountId);
        }

        return $this->settingsCache[$accountId][$camelKey] ?? null;
    }

    /**
     * Check whether a member/client email of the given type may be sent:
     * the master switch AND the per-type toggle must both be enabled.
     *
     * @param int $accountId
     * @param string $camelKey e.g. 'emailPaymentConfirmation'
     * @return bool
     */
    private function shouldSendEmail(int $accountId, string $camelKey): bool
    {
        if (!(bool) ($this->getSetting($accountId, 'emailNotificationsEnabled') ?? true)) {
            return false;
        }

        return (bool) ($this->getSetting($accountId, $camelKey) ?? true);
    }

    /**
     * Check if in-app membership expiry notifications are enabled.
     *
     * @return bool
     */
    private function shouldSendMembershipExpiryNotification(int $accountId): bool
    {
        return (bool) ($this->getSetting($accountId, 'notifyMembershipExpiry') ?? true);
    }

    /**
     * Check if in-app payment alert notifications are enabled.
     *
     * @return bool
     */
    private function shouldSendPaymentAlertNotification(int $accountId): bool
    {
        return (bool) ($this->getSetting($accountId, 'notifyPaymentReceived') ?? true);
    }

    /**
     * Check if in-app new registration notifications are enabled.
     *
     * @return bool
     */
    private function shouldSendNewRegistrationNotification(int $accountId): bool
    {
        return (bool) ($this->getSetting($accountId, 'notifyNewRegistration') ?? true);
    }

    /**
     * Create notification for expiring membership.
     *
     * In-app notification and customer email are gated independently:
     * the in-app toggle (notifyMembershipExpiry) and the email toggles
     * (emailNotificationsEnabled + emailMembershipExpiring).
     *
     * @param CustomerMembership $membership
     * @return void
     */
    public function createMembershipExpiringNotification(CustomerMembership $membership): void
    {
        $accountId = $membership->account_id;

        $sendInApp = $this->shouldSendMembershipExpiryNotification($accountId);
        $sendEmail = $this->shouldSendEmail($accountId, 'emailMembershipExpiring');

        if (!$sendInApp && !$sendEmail) {
            Log::info('Membership expiry notifications disabled', [
                'customer_id' => $membership->customer_id,
                'membership_id' => $membership->id
            ]);
            return;
        }

        try {
            $customer = $membership->customer;

            // Each lane dedupes against its own history, so the channels stay
            // independent no matter which toggles are on.

            // In-app lane: skip if an identical notification was created in the last 24h.
            if ($sendInApp && $this->notificationRepository->notificationExists(
                $accountId,
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
                $sendInApp = false;
            }

            // Email lane: skip if a reminder email was already queued recently
            // (tracked in tb_notification_email_logs, independent of in-app records).
            if ($sendEmail && $this->emailLogRepository->emailExists(
                $accountId,
                NotificationConstant::TYPE_MEMBERSHIP_EXPIRING,
                $customer->id,
                $membership->id,
                self::EMAIL_DEDUP_HOURS
            )) {
                Log::info('Membership expiring email already sent recently', [
                    'customer_id' => $customer->id,
                    'membership_id' => $membership->id
                ]);
                $sendEmail = false;
            }

            if (!$sendInApp && !$sendEmail) {
                return;
            }

            // Send email to customer (if member emails are enabled), then record
            // it in the email log so re-runs within the window don't resend.
            if ($sendEmail && $this->sendMembershipExpiringEmail($customer, $membership)) {
                $this->emailLogRepository->log(
                    $accountId,
                    NotificationConstant::TYPE_MEMBERSHIP_EXPIRING,
                    $customer->id,
                    $membership->id
                );
            }

            // Create global in-app notification (if in-app alerts are enabled)
            if ($sendInApp) {
                $this->notificationRepository->create([
                    'account_id' => $accountId,
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
            }

            // When user management is implemented, uncomment this to create notifications for admin users
            // $adminUsers = User::where('is_admin', true)->get();
            // foreach ($adminUsers as $admin) {
            //     $this->notificationRepository->create([...]);
            // }

            Log::info('Membership expiring notification processed', [
                'customer_id' => $customer->id,
                'membership_id' => $membership->id,
                'in_app' => $sendInApp,
                'email' => $sendEmail
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
     * In-app notification and customer email are gated independently:
     * the in-app toggle (notifyPaymentReceived) and the email toggles
     * (emailNotificationsEnabled + emailPaymentConfirmation).
     *
     * @param CustomerPayment $payment
     * @return void
     */
    public function createPaymentReceivedNotification(CustomerPayment $payment): void
    {
        $accountId = $payment->account_id;

        $sendInApp = $this->shouldSendPaymentAlertNotification($accountId);
        $sendEmail = $this->shouldSendEmail($accountId, 'emailPaymentConfirmation');

        if (!$sendInApp && !$sendEmail) {
            Log::info('Payment alert notifications disabled', [
                'payment_id' => $payment->id,
                'customer_id' => $payment->customer_id
            ]);
            return;
        }

        try {
            $customer = $payment->customer;
            $bill = $payment->bill;

            // Send email to customer (if member emails are enabled)
            if ($sendEmail) {
                $this->sendPaymentConfirmationEmail($customer, $payment);
            }

            // Create global in-app notification (if in-app alerts are enabled)
            if ($sendInApp) {
                $this->notificationRepository->create([
                    'account_id' => $accountId,
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
            }

            // When user management is implemented, uncomment this to create notifications for admin users
            // $adminUsers = User::where('is_admin', true)->get();
            // foreach ($adminUsers as $admin) {
            //     $this->notificationRepository->create([...]);
            // }

            Log::info('Payment received notification processed', [
                'customer_id' => $customer->id,
                'payment_id' => $payment->id,
                'in_app' => $sendInApp,
                'email' => $sendEmail
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
     * In-app notification and customer email are gated independently:
     * the in-app toggle (notifyNewRegistration) and the email toggles
     * (emailNotificationsEnabled + emailCustomerRegistration).
     *
     * @param Customer $customer
     * @return void
     */
    public function createCustomerRegisteredNotification(Customer $customer): void
    {
        $accountId = $customer->account_id;

        $sendInApp = $this->shouldSendNewRegistrationNotification($accountId);
        $sendEmail = $this->shouldSendEmail($accountId, 'emailCustomerRegistration');

        if (!$sendInApp && !$sendEmail) {
            Log::info('New registration notifications disabled', [
                'customer_id' => $customer->id
            ]);
            return;
        }

        try {
            // Send welcome email to customer (if member emails are enabled)
            if ($sendEmail) {
                $this->sendCustomerRegistrationEmail($customer);
            }

            // Create global in-app notification (if in-app alerts are enabled)
            if ($sendInApp) {
                $membership = $customer->memberships()->latest()->first();

                $this->notificationRepository->create([
                    'account_id' => $accountId,
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
            }

            // When user management is implemented, uncomment this to create notifications for admin users
            // $adminUsers = User::where('is_admin', true)->get();
            // foreach ($adminUsers as $admin) {
            //     $this->notificationRepository->create([...]);
            // }

            Log::info('Customer registered notification processed', [
                'customer_id' => $customer->id,
                'in_app' => $sendInApp,
                'email' => $sendEmail
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
     * @return bool true when the email job was queued
     */
    public function sendMembershipExpiringEmail(Customer $customer, CustomerMembership $membership): bool
    {
        if (!$customer->email) {
            Log::warning('Customer has no email address', ['customer_id' => $customer->id]);
            return false;
        }

        try {
            // Dispatch job to queue with a staggered delay to respect Mailtrap's rate limit
            $delay = now()->addSeconds($this->getEmailQueueDelay());
            \App\Jobs\SendMembershipExpiringEmail::dispatch($customer->id, $membership->id)->delay($delay);

            Log::info('Membership expiring email queued', [
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'delay_seconds' => $delay->diffInSeconds(now())
            ]);

            return true;
        } catch (\Throwable $th) {
            Log::error('Error queuing membership expiring email', [
                'error' => $th->getMessage(),
                'customer_id' => $customer->id
            ]);

            return false;
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
    public function getNotifications(int $accountId, int $page = 1, int $limit = 20): array
    {
        $offset = (int) (($page - 1) * $limit);
        $limit = (int) max(1, $limit);

        $baseQuery = Notification::where('account_id', $accountId)
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
    public function getUnreadCount(int $accountId): int
    {
        return $this->notificationRepository->getUnreadCountGlobal($accountId);
    }

    /**
     * Mark notification as read.
     *
     * @param int $notificationId
     * @return Notification
     */
    public function markAsRead(int $notificationId, int $accountId): Notification
    {
        return $this->notificationRepository->markAsRead($notificationId, $accountId);
    }

    /**
     * Mark all notifications as read (global).
     *
     * @return int
     */
    public function markAllAsRead(int $accountId): int
    {
        return $this->notificationRepository->markAllAsReadGlobal($accountId);
    }
}
