<?php

namespace App\Constant;

/**
 * How a member profile came to exist. Stored on tb_customers.registration_source.
 *
 * Public registrations bypass any human review, so this is the audit trail that
 * lets staff find and triage them separately from staff-entered records.
 */
class CustomerRegistrationSourceConstant
{
    /** Created by a staff member from the admin app. */
    public const STAFF = 'staff';

    /** Self-entered on the on-site kiosk tablet, under a staff session. */
    public const KIOSK = 'kiosk';

    /** Self-entered through the public /join/{publicCode} link. */
    public const PUBLIC_LINK = 'public';

    /**
     * Every valid registration source.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::STAFF, self::KIOSK, self::PUBLIC_LINK];
    }
}
