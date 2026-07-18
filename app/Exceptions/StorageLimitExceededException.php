<?php

namespace App\Exceptions;

/**
 * @deprecated Use App\Exceptions\QuotaExceededException instead. Kept only as a
 * thin subclass for safety; this file can be deleted.
 */
class StorageLimitExceededException extends QuotaExceededException
{
}
