<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when consuming a metered resource (storage, SMS credits, …) would
 * push an account past its quota. Rendered as a 403 JSON response in
 * bootstrap/app.php — controllers never catch it.
 */
class QuotaExceededException extends Exception
{
    /**
     * @param string $message User-facing message describing which quota was hit.
     */
    public function __construct(string $message = 'Quota reached. Free up space or upgrade your plan.')
    {
        parent::__construct($message);
    }
}
