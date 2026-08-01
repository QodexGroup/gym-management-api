<?php

namespace App\Constant;

class StorageConstant
{
    /**
     * Units are KILOBYTES (KB) throughout, matching tb_customer_files.file_size.
     */
    const BYTES_PER_KB = 1024;
    const KB_PER_MB = 1024;
    const KB_PER_GB = 1024 * 1024; // 1,048,576 KB

    /**
     * Flat per-account storage cap: 5 GB.
     */
    const DEFAULT_STORAGE_LIMIT_GB = 5;
    const DEFAULT_STORAGE_LIMIT_KB = self::DEFAULT_STORAGE_LIMIT_GB * self::KB_PER_GB; // 5,242,880 KB

    /**
     * Percentage of the quota at which the UI should warn the account owner.
     */
    const WARNING_THRESHOLD_PERCENT = 90;

    /**
     * R2 prefixes (relative to the account folder) that are platform-billing
     * artifacts rather than the gym's own data — e.g. proof-of-payment receipts
     * for subscription invoices and reactivation fees. These are never counted
     * toward the account's storage quota, by the live counter or the reconcile.
     */
    const QUOTA_EXCLUDED_PREFIXES = [
        'subscription-receipts/',
    ];

    /**
     * MIME types permitted for direct-to-R2 uploads (mirrors the presigned rule).
     */
    const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];
}
