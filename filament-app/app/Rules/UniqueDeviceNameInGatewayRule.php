<?php

namespace App\Rules;

use App\Models\Device;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueDeviceNameInGatewayRule implements ValidationRule
{
    protected int $gatewayId;
    protected ?int $excludeDeviceId;

    public function __construct(int $gatewayId, ?int $excludeDeviceId = null)
    {
        $this->gatewayId = $gatewayId;
        $this->excludeDeviceId = $excludeDeviceId;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = Device::where('gateway_id', $this->gatewayId)
            ->where('device_name', $value);
        
        if ($this->excludeDeviceId) {
            $query->where('id', '!=', $this->excludeDeviceId);
        }
        
        if ($query->exists()) {
            $fail('A device with this name already exists in this gateway.');
        }
    }
}