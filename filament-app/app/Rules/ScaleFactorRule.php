<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ScaleFactorRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return; // Allow null values
        }

        if (!is_numeric($value)) {
            $fail('Scale factor must be a number.');
            return;
        }

        $scale = (float) $value;
        
        if ($scale <= 0) {
            $fail('Scale factor must be greater than 0.');
            return;
        }
        
        if ($scale > 1000000) {
            $fail('Scale factor cannot exceed 1,000,000.');
        }
    }
}