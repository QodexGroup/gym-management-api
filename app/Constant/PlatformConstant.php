<?php

namespace App\Constant;

use App\Models\Account\AccountInvoice;
use App\Models\Account\AccountSubscriptionPlan;

/**
 * Vocabulary of the platform admin contract.
 *
 * The console speaks one language across every product, so this class is where
 * GymHub's internal names are translated into the contract's. Nothing outside
 * the Platform namespaces should need to know that `payment_transaction` holds
 * a fully qualified class name, for instance — that is a GymHub detail.
 */
class PlatformConstant
{
    /* ---- Payment transaction kinds ---- */

    const TXN_INVOICE_PAYMENT = 'invoice_payment';
    const TXN_SUBSCRIPTION_UPGRADE = 'subscription_upgrade';
    const TXN_REACTIVATION_FEE = 'reactivation_fee';
    const TXN_UNKNOWN = 'unknown';

    /**
     * GymHub stores `payment_transaction` as a model class name — except for
     * reactivation fees, which are the literal string 'Reactivation Fee'.
     * The console must never see either form.
     */
    const TRANSACTION_KINDS = [
        AccountInvoice::class => self::TXN_INVOICE_PAYMENT,
        AccountSubscriptionPlan::class => self::TXN_SUBSCRIPTION_UPGRADE,
        'Reactivation Fee' => self::TXN_REACTIVATION_FEE,
    ];

    /* ---- Derived account status ---- */

    const ACCOUNT_ACTIVE = 'active';
    const ACCOUNT_TRIAL = 'trial';
    const ACCOUNT_LOCKED = 'locked';
    const ACCOUNT_DEACTIVATED = 'deactivated';

    const ACCOUNT_STATUSES = [
        self::ACCOUNT_ACTIVE,
        self::ACCOUNT_TRIAL,
        self::ACCOUNT_LOCKED,
        self::ACCOUNT_DEACTIVATED,
    ];

    /* ---- Invoice status as the contract reports it ---- */

    const INVOICE_PENDING = 'pending';
    const INVOICE_PAID = 'paid';
    const INVOICE_OVERDUE = 'overdue';
    const INVOICE_VOID = 'void';

    const INVOICE_STATUSES = [
        self::INVOICE_PENDING,
        self::INVOICE_PAID,
        self::INVOICE_OVERDUE,
        self::INVOICE_VOID,
    ];

    /* ---- Account list ordering ---- */

    /**
     * Sort keys name the FIELD of the contract they order by, not a column, so
     * the console can offer them without knowing GymHub's schema.
     *
     * Direction is not a separate parameter: there is only one reason to order
     * by each of these. Coverage end is asked for to find what expires first,
     * so it ascends; the join date is asked for to see recent signups, so it
     * descends. A `direction` knob would only let a caller ask for the useless
     * half of each.
     */
    const ACCOUNT_SORT_CREATED_AT = 'createdAt';
    const ACCOUNT_SORT_COVERAGE_ENDS_AT = 'coverageEndsAt';

    const ACCOUNT_SORTS = [
        self::ACCOUNT_SORT_CREATED_AT,
        self::ACCOUNT_SORT_COVERAGE_ENDS_AT,
    ];

    /**
     * Translate a stored `payment_transaction` into the contract's kind.
     *
     * @param string|null $paymentTransaction
     *
     * @return string
     */
    public static function transactionKind(?string $paymentTransaction): string
    {
        if ($paymentTransaction === null) {
            return self::TXN_UNKNOWN;
        }

        return self::TRANSACTION_KINDS[$paymentTransaction] ?? self::TXN_UNKNOWN;
    }
}
