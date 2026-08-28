<?php

namespace App\Constant;

/**
 * Limits and field names for the public member self-registration endpoint.
 *
 * Customer creation is completely unmetered in this system — max_customers
 * exists on subscription_plans but is never read anywhere — so throttling and
 * duplicate rejection are the ONLY defences against a junk-registration flood.
 * Treat these numbers as a security control, not a convenience.
 */
class PublicRegistrationConstant
{
    /** Named rate limiter registered in AppServiceProvider. */
    public const RATE_LIMITER = 'public-registration';

    /**
     * Submissions per minute from a single IP.
     *
     * Kept deliberately low. This mainly slows scripted probing of the
     * duplicate-phone response, which is an enumeration oracle by design.
     */
    public const MAX_PER_MINUTE_PER_IP = 3;

    /**
     * Submissions per day for a single gym, across all IPs.
     *
     * This is the real backstop. Per-IP limits are weak in the Philippines,
     * where mobile data and CGNAT put many genuine users behind one address and
     * a determined bot rotates addresses freely. A per-gym daily ceiling caps
     * the damage even from a fully distributed flood.
     */
    public const MAX_PER_DAY_PER_GYM = 40;

    /** Requests per minute for the read-only gym info endpoint. */
    public const MAX_INFO_PER_MINUTE_PER_IP = 30;

    /**
     * Hidden form field that real people never fill in.
     *
     * A populated honeypot gets the ordinary success response with nothing
     * written, so a bot cannot tell it was rejected.
     */
    public const HONEYPOT_FIELD = 'website';
}
