<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PortRangeRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_numeric($value)) {
            $fail('Port must be a number.');
            return;
        }

        $port = (int) $value;
        
        if ($port < 1 || $port > 65535) {
            $fail('Port must be between 1 and 65535.');
        }
    }
}