<?php

namespace App\Rules;

use App\Support\EmailValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!EmailValidator::isValid(is_string($value) ? $value : null)) {
            $fail('Please provide a valid email address.');
        }
    }
}

