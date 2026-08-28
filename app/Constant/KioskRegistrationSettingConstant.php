<?php

namespace App\Constant;

/**
 * Public member self-registration settings group (stored in the generic
 * account_system_settings EAV store). These control the public
 * /join/{publicCode} registration link.
 *
 * kiosk_registration_enabled is the master switch AND the only kill switch:
 * the account's public_code is permanent and cannot be rotated, so turning
 * this off is the sole way to disable a link that has been shared or printed.
 * It defaults to false so existing gyms must opt in deliberately.
 */
class KioskRegistrationSettingConstant
{
    /**
     * Definition of every public-registration setting: the stored snake_case
     * set_key mapped to its API camelCase name, value type (for casting), and
     * default value.
     *
     * @return array<string, array{camel: string, type: string, default: mixed}>
     */
    public static function definitions(): array
    {
        return [
            'kiosk_registration_enabled' => ['camel' => 'kioskRegistrationEnabled', 'type' => 'bool', 'default' => false],
            'kiosk_registration_require_email' => ['camel' => 'kioskRegistrationRequireEmail', 'type' => 'bool', 'default' => false],
            'kiosk_registration_require_address' => ['camel' => 'kioskRegistrationRequireAddress', 'type' => 'bool', 'default' => false],
            'kiosk_registration_require_emergency_contact' => ['camel' => 'kioskRegistrationRequireEmergencyContact', 'type' => 'bool', 'default' => false],
            'kiosk_registration_welcome_text' => ['camel' => 'kioskRegistrationWelcomeText', 'type' => 'string', 'default' => ''],
            'kiosk_registration_success_text' => ['camel' => 'kioskRegistrationSuccessText', 'type' => 'string', 'default' => ''],
        ];
    }
}
