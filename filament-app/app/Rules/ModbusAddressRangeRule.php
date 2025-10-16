<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ModbusAddressRangeRule implements ValidationRule
{
    protected int $count;

    public function __construct(int $count = 1)
    {
        $this->count = $count;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_numeric($value)) {
            $fail('Register address must be a number.');
            return;
        }

        $startAddress = (int) $value;
        $endAddress = $startAddress + $this->count - 1;
        
        if ($startAddress < 0 || $startAddress > 65535) {
            $fail('Register address must be between 0 and 65535.');
            return;
        }
        
        if ($endAddress > 65535) {
            $fail("Register address range ({$startAddress}-{$endAddress}) exceeds Modbus limit (65535).");
        }
    }

    /**
     * Set the count for range validation.
     */
    public function setCount(int $count): self
    {
        $this->count = $count;
        return $this;
    }
}