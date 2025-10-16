<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ModbusAddressRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_numeric($value)) {
            $fail('Register address must be a number.');
            return;
        }

        $address = (int) $value;
        
        if ($address < 0 || $address > 65535) {
            $fail('Register address must be between 0 and 65535.');
        }
    }
}