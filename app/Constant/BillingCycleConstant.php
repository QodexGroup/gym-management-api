<?php

namespace App\Constant;

/**
 * Billing cycle day constants.
 */
class BillingCycleConstant
{
    /** Billing cycle day of month (due date). */
    const CYCLE_DAY_DUE = 5;

    /** Lock account if unpaid by this day. */
    const CYCLE_DAY_LOCK = 10;
}
