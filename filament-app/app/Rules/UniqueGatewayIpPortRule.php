<?php

namespace App\Rules;

use App\Models\Gateway;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueGatewayIpPortRule implements ValidationRule
{
    protected ?int $excludeId;
    protected ?int $port;

    public function __construct(?int $excludeId = null, ?int $port = null)
    {
        $this->excludeId = $excludeId;
        $this->port = $port;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Get port from constructor or request
        $port = $this->port ?? request('port', 502);
        
        if (!$port) {
            return; // Skip validation if no port is provided
        }

        $query = Gateway::where('ip_address', $value)
            ->where('port', $port);
        
        if ($this->excludeId) {
            $query->where('id', '!=', $this->excludeId);
        }
        
        if ($query->exists()) {
            $fail('A gateway with this IP address and port combination already exists.');
        }
    }

    /**
     * Set the port for validation.
     */
    public function setPort(int $port): self
    {
        $this->port = $port;
        return $this;
    }
}