<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RegisterCountForDataTypeRule implements ValidationRule
{
    protected string $dataType;

    public function __construct(string $dataType)
    {
        $this->dataType = $dataType;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_numeric($value)) {
            $fail('Register count must be a number.');
            return;
        }

        $count = (int) $value;
        $requiredCount = $this->getRequiredRegisterCount($this->dataType);
        
        if ($count < $requiredCount) {
            $fail("Register count must be at least {$requiredCount} for {$this->dataType} data type.");
        }
    }

    /**
     * Get the required register count for a data type.
     */
    protected function getRequiredRegisterCount(string $dataType): int
    {
        return match($dataType) {
            'int16', 'uint16' => 1,
            'int32', 'uint32', 'float32' => 2,
            'float64' => 4,
            default => 2
        };
    }

    /**
     * Set the data type for validation.
     */
    public function setDataType(string $dataType): self
    {
        $this->dataType = $dataType;
        return $this;
    }
}