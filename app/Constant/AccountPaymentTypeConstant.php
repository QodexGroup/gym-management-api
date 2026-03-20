<?php

namespace App\Constant;

class AccountPaymentTypeConstant
{
    const GCASH = 'GCASH';
    const MAYA = 'MAYA';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::GCASH,
            self::MAYA,
        ];
    }
}
