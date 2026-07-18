<?php

use App\Constant\ResourceKeyConstant;
use App\Constant\StorageConstant;

return [
    /*
    |--------------------------------------------------------------------------
    | Default per-account resource limits
    |--------------------------------------------------------------------------
    | Keyed by resource_key (see App\Constant\ResourceKeyConstant). Units are the
    | resource's base unit (storage: KB; future sms_credits: credits).
    | A per-account override lives in account_usages.limit_override; when null,
    | the default below applies. Promote to subscription_plans later for tiered
    | limits without touching usage tracking.
    */
    'defaults' => [
        ResourceKeyConstant::STORAGE => StorageConstant::DEFAULT_STORAGE_LIMIT_KB, // 5 GB in KB
    ],
];
