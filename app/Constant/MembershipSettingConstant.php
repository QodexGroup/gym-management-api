<?php

namespace App\Constant;

class MembershipSettingConstant
{
    // grant_membership_on
    const GRANT_ON_FULL_PAYMENT = 'full_payment';
    const GRANT_ON_FIRST_PAYMENT = 'first_payment';

    // reactivation_promo_unit
    const PROMO_UNIT_DAYS = 'days';
    const PROMO_UNIT_MONTHS = 'months';

    // plan_change_mode
    const PLAN_CHANGE_NEXT_RENEWAL = 'next_renewal';
    const PLAN_CHANGE_IMMEDIATE_PRORATION = 'immediate_proration';

    // downgrade_credit_mode (only relevant under immediate proration)
    const DOWNGRADE_FORFEIT = 'forfeit';
    const DOWNGRADE_EXTEND_DAYS = 'extend_days';

    // billing_anchor
    const ANCHOR_ANNIVERSARY = 'anniversary';
    const ANCHOR_FIXED_DAY = 'fixed_day';

    /**
     * Definition of every membership setting: the stored snake_case set_key mapped to
     * its API camelCase name, value type (for casting), and default value.
     *
     * @return array<string, array{camel: string, type: string, default: mixed}>
     */
    public static function definitions(): array
    {
        return [
            'grant_membership_on' => ['camel' => 'grantMembershipOn', 'type' => 'string', 'default' => self::GRANT_ON_FIRST_PAYMENT],
            'allow_partial_payments' => ['camel' => 'allowPartialPayments', 'type' => 'bool', 'default' => true],
            'grace_period_days' => ['camel' => 'gracePeriodDays', 'type' => 'int', 'default' => 7],
            'require_membership_for_class_booking' => ['camel' => 'requireMembershipForClassBooking', 'type' => 'bool', 'default' => true],
            'allow_class_booking_during_grace' => ['camel' => 'allowClassBookingDuringGrace', 'type' => 'bool', 'default' => false],
            'require_reactivation_fee' => ['camel' => 'requireReactivationFee', 'type' => 'bool', 'default' => true],
            'reactivation_fee_amount' => ['camel' => 'reactivationFeeAmount', 'type' => 'float', 'default' => 0],
            'grant_reactivation_promo' => ['camel' => 'grantReactivationPromo', 'type' => 'bool', 'default' => true],
            'reactivation_promo_length' => ['camel' => 'reactivationPromoLength', 'type' => 'int', 'default' => 1],
            'reactivation_promo_unit' => ['camel' => 'reactivationPromoUnit', 'type' => 'string', 'default' => self::PROMO_UNIT_MONTHS],
            'plan_change_mode' => ['camel' => 'planChangeMode', 'type' => 'string', 'default' => self::PLAN_CHANGE_NEXT_RENEWAL],
            'downgrade_credit_mode' => ['camel' => 'downgradeCreditMode', 'type' => 'string', 'default' => self::DOWNGRADE_EXTEND_DAYS],
            'allow_manual_membership_bills' => ['camel' => 'allowManualMembershipBills', 'type' => 'bool', 'default' => true],
            'allow_pay_previous_cycle_bills' => ['camel' => 'allowPayPreviousCycleBills', 'type' => 'bool', 'default' => true],
            'allow_edit_previous_cycle_bills' => ['camel' => 'allowEditPreviousCycleBills', 'type' => 'bool', 'default' => false],
            'billing_anchor' => ['camel' => 'billingAnchor', 'type' => 'string', 'default' => self::ANCHOR_ANNIVERSARY],
            'fixed_billing_day' => ['camel' => 'fixedBillingDay', 'type' => 'int', 'default' => null],
        ];
    }
}
