<?php

namespace App\Rules;

use App\Models\Register;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueRegisterAddressInDeviceRule implements ValidationRule
{
    protected int $deviceId;
    protected ?int $excludeRegisterId;

    public function __construct(int $deviceId, ?int $excludeRegisterId = null)
    {
        $this->deviceId = $deviceId;
        $this->excludeRegisterId = $excludeRegisterId;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_numeric($value)) {
            return; // Let other rules handle non-numeric validation
        }

        $address = (int) $value;
        
        $query = Register::where('device_id', $this->deviceId)
            ->where('register_address', $address);
        
        if ($this->excludeRegisterId) {
            $query->where('id', '!=', $this->excludeRegisterId);
        }
        
        if ($query->exists()) {
            $fail("A register with address {$address} already exists for this device.");
        }
    }
}