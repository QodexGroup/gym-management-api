<?php

namespace Database\Seeders;

use App\Constant\NotificationConstant;
use App\Models\Core\Notification;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing notifications
        Notification::truncate();

        $notifications = [];

        // 1. Customer Registration Notifications (5 notifications)
        for ($i = 1; $i <= 5; $i++) {
            $notifications[] = [
                'account_id' => 1,
                'user_id' => null, // Global notification
                'type' => NotificationConstant::TYPE_CUSTOMER_REGISTERED,
                'title' => 'New Customer Registered',
                'message' => "John Doe #{$i} has been registered",
                'data' => json_encode([
                    'customer_id' => $i,
                    'customer_name' => "John Doe #{$i}",
                    'membership_plan' => 'Premium Membership',
                    'registration_date' => Carbon::now()->subDays($i)->format('Y-m-d'),
                ]),
                'read_at' => $i > 2 ? Carbon::now()->subHours($i) : null, // First 2 are unread
                'created_at' => Carbon::now()->subDays($i),
                'updated_at' => Carbon::now()->subDays($i),
            ];
        }

        // 2. Membership Expiring Notifications (5 notifications)
        for ($i = 1; $i <= 5; $i++) {
            $daysUntilExpiry = 7 - $i; // 6, 5, 4, 3, 2 days remaining
            $notifications[] = [
                'account_id' => 1,
                'user_id' => null,
                'type' => NotificationConstant::TYPE_MEMBERSHIP_EXPIRING,
                'title' => 'Membership Expiring Soon',
                'message' => "Sarah Smith #{$i}'s membership expires on " . Carbon::now()->addDays($daysUntilExpiry)->format('M d, Y'),
                'data' => json_encode([
                    'customer_id' => 10 + $i,
                    'customer_name' => "Sarah Smith #{$i}",
                    'membership_id' => 20 + $i,
                    'membership_plan' => 'Gold Membership',
                    'expiration_date' => Carbon::now()->addDays($daysUntilExpiry)->format('Y-m-d'),
                    'days_remaining' => $daysUntilExpiry,
                ]),
                'read_at' => $i > 3 ? Carbon::now()->subHours($i) : null, // First 3 are unread
                'created_at' => Carbon::now()->subHours($i * 2),
                'updated_at' => Carbon::now()->subHours($i * 2),
            ];
        }

        // 3. Payment Received Notifications (8 notifications)
        $paymentMethods = ['cash', 'credit_card', 'bank_transfer', 'gcash'];
        $amounts = [1500, 2500, 3000, 1200, 1800, 2200, 2800, 3500];
        
        for ($i = 1; $i <= 8; $i++) {
            $amount = $amounts[$i - 1];
            $method = $paymentMethods[($i - 1) % count($paymentMethods)];
            
            $notifications[] = [
                'account_id' => 1,
                'user_id' => null,
                'type' => NotificationConstant::TYPE_PAYMENT_RECEIVED,
                'title' => 'Payment Received',
                'message' => "Payment of ₱{$amount} received from Mike Johnson #{$i}",
                'data' => json_encode([
                    'customer_id' => 30 + $i,
                    'customer_name' => "Mike Johnson #{$i}",
                    'payment_id' => 40 + $i,
                    'bill_id' => 50 + $i,
                    'amount' => $amount,
                    'payment_method' => $method,
                    'payment_date' => Carbon::now()->subHours($i * 3)->format('Y-m-d'),
                    'reference_number' => 'REF-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                ]),
                'read_at' => $i > 4 ? Carbon::now()->subHours($i) : null, // First 4 are unread
                'created_at' => Carbon::now()->subHours($i * 3),
                'updated_at' => Carbon::now()->subHours($i * 3),
            ];
        }

        // Insert all notifications
        Notification::insert($notifications);

        $this->command->info('Created ' . count($notifications) . ' notifications:');
        $this->command->info('- 5 Customer Registration notifications');
        $this->command->info('- 5 Membership Expiring notifications');
        $this->command->info('- 8 Payment Received notifications');
        
        $unreadCount = Notification::whereNull('read_at')->count();
        $this->command->info("Total unread: {$unreadCount}");
    }
}
