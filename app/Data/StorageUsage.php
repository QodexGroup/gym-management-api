<?php

namespace App\Data;

/**
 * Storage quota snapshot for an account. All numeric values are in KB;
 * the *Label fields are human-readable (KB/MB/GB) for direct display.
 */
class StorageUsage
{
    public float $usedKb;
    public float $limitKb;
    public float $remainingKb;
    public float $usedPercent;
    public bool $isFull;
    public bool $isNearLimit;
    public string $usedLabel;
    public string $limitLabel;
    public string $remainingLabel;
}
