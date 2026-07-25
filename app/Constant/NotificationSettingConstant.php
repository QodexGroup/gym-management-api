<?php

namespace App\Constant;

/**
 * In-app notification settings group (stored in the generic account_system_settings
 * EAV store). These gate the creation of in-app notifications only — customer-facing
 * emails are gated separately by {@see EmailNotificationSettingConstant}.
 */
class NotificationSettingConstant
{
    /**
     * Definition of every in-app notification setting: the stored snake_case set_key
     * mapped to its API camelCase name, value type (for casting), and default value.
     *
     * @return array<string, array{camel: string, type: string, default: mixed}>
     */
    public static function definitions(): array
    {
        return [
            'notify_membership_expiry' => ['camel' => 'notifyMembershipExpiry', 'type' => 'bool', 'default' => true],
            'notify_payment_received' => ['camel' => 'notifyPaymentReceived', 'type' => 'bool', 'default' => true],
            'notify_new_registration' => ['camel' => 'notifyNewRegistration', 'type' => 'bool', 'default' => true],
        ];
    }
}
