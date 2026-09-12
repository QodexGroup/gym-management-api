<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Admin Contract
    |--------------------------------------------------------------------------
    |
    | Configuration for the machine-to-machine /api/platform/* surface that the
    | operations console calls. This is deliberately independent of Firebase:
    | the caller is another server, not a person, and no tenant scoping applies.
    |
    */

    /** Identifies this product to the console. Matches projects.product_key. */
    'product' => env('PLATFORM_PRODUCT', 'gymhub'),

    /** Contract version, reported by /platform/health. */
    'version' => env('PLATFORM_CONTRACT_VERSION', 'v1'),

    /*
    | SHA-256 of the service token, never the token itself. Generate with:
    |   php artisan platform:token
    | Leaving this empty disables the whole surface — every request 403s, which
    | is the correct posture for an environment that has not been set up.
    */
    'service_token_hash' => env('PLATFORM_SERVICE_TOKEN_HASH'),

    /** Optional CIDR-free IP allowlist. Empty means any source address. */
    'ip_allowlist' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PLATFORM_IP_ALLOWLIST', '')),
    ))),

    /*
    | Refuse plaintext HTTP. The service token travels on every request, so this
    | should be true anywhere the app is reachable off localhost.
    */
    'require_https' => filter_var(env('PLATFORM_REQUIRE_HTTPS', false), FILTER_VALIDATE_BOOL),

    /** How long a minted receipt URL stays valid. */
    'receipt_url_ttl' => env('PLATFORM_RECEIPT_URL_TTL', '+15 minutes'),

    /** Default and maximum page size for paginated platform reads. */
    'page_size' => (int) env('PLATFORM_PAGE_SIZE', 25),
    'max_page_size' => (int) env('PLATFORM_MAX_PAGE_SIZE', 100),

];
