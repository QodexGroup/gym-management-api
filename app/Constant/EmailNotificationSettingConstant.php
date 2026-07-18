<?php

namespace App\Constant;

/**
 * Member/client email notification settings group (stored in the generic
 * account_system_settings EAV store). These gate the emails sent to customers
 * (expiry reminders, payment confirmations, welcome emails). The master switch
 * email_notifications_enabled must be ON for any per-type email to send.
 */
class EmailNotificationSettingConstant
{
    /**
     * Definition of every email notification setting: the stored snake_case set_key
     * mapped to its API camelCase name, value type (for casting), and default value.
     *
     * @return array<string, array{camel: string, type: string, default: mixed}>
     */
    public static function definitions(): array
    {
        return [
            'email_notifications_enabled' => ['camel' => 'emailNotificationsEnabled', 'type' => 'bool', 'default' => true],
            'email_membership_expiring' => ['camel' => 'emailMembershipExpiring', 'type' => 'bool', 'default' => true],
            'email_payment_confirmation' => ['camel' => 'emailPaymentConfirmation', 'type' => 'bool', 'default' => true],
            'email_customer_registration' => ['camel' => 'emailCustomerRegistration', 'type' => 'bool', 'default' => true],
        ];
    }
}
