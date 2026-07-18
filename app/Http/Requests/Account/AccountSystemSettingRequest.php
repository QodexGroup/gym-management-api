<?php

namespace App\Http\Requests\Account;

use App\Constant\MembershipSettingConstant;
use App\Http\Requests\GenericRequest;

/**
 * Validation for the generic account settings endpoint. Rules cover every known
 * setting key (all `sometimes`, so any subset can be sent). Add new settings'
 * rules here as more setting groups are introduced.
 */
class AccountSystemSettingRequest extends GenericRequest
{
    public function rules(): array
    {
        return [
            // Membership settings group
            'grantMembershipOn' => ['sometimes', 'in:' . MembershipSettingConstant::GRANT_ON_FULL_PAYMENT . ',' . MembershipSettingConstant::GRANT_ON_FIRST_PAYMENT],
            'allowPartialPayments' => ['sometimes', 'boolean'],
            'gracePeriodDays' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'requireMembershipForClassBooking' => ['sometimes', 'boolean'],
            'allowClassBookingDuringGrace' => ['sometimes', 'boolean'],
            'requireReactivationFee' => ['sometimes', 'boolean'],
            'reactivationFeeAmount' => ['sometimes', 'numeric', 'min:0'],
            'grantReactivationPromo' => ['sometimes', 'boolean'],
            'reactivationPromoLength' => ['sometimes', 'integer', 'min:1', 'max:36'],
            'reactivationPromoUnit' => ['sometimes', 'in:' . MembershipSettingConstant::PROMO_UNIT_DAYS . ',' . MembershipSettingConstant::PROMO_UNIT_MONTHS],
            'planChangeMode' => ['sometimes', 'in:' . MembershipSettingConstant::PLAN_CHANGE_NEXT_RENEWAL . ',' . MembershipSettingConstant::PLAN_CHANGE_IMMEDIATE_PRORATION],
            'downgradeCreditMode' => ['sometimes', 'in:' . MembershipSettingConstant::DOWNGRADE_FORFEIT . ',' . MembershipSettingConstant::DOWNGRADE_EXTEND_DAYS],
            'allowManualMembershipBills' => ['sometimes', 'boolean'],
            'allowPayPreviousCycleBills' => ['sometimes', 'boolean'],
            'allowEditPreviousCycleBills' => ['sometimes', 'boolean'],
            'billingAnchor' => ['sometimes', 'in:' . MembershipSettingConstant::ANCHOR_ANNIVERSARY . ',' . MembershipSettingConstant::ANCHOR_FIXED_DAY],
            'fixedBillingDay' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:28'],

            // In-app notification settings group
            'notifyMembershipExpiry' => ['sometimes', 'boolean'],
            'notifyPaymentReceived' => ['sometimes', 'boolean'],
            'notifyNewRegistration' => ['sometimes', 'boolean'],

            // Member/client email notification settings group
            'emailNotificationsEnabled' => ['sometimes', 'boolean'],
            'emailMembershipExpiring' => ['sometimes', 'boolean'],
            'emailPaymentConfirmation' => ['sometimes', 'boolean'],
            'emailCustomerRegistration' => ['sometimes', 'boolean'],
        ];
    }
}
