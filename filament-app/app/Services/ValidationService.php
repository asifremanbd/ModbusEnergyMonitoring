<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\Device;
use App\Models\Register;
use App\Rules\ModbusAddressRule;
use App\Rules\ModbusAddressRangeRule;
use App\Rules\PortRangeRule;
use App\Rules\UniqueGatewayIpPortRule;
use App\Rules\ScaleFactorRule;
use App\Rules\RegisterCountForDataTypeRule;
use App\Rules\UniqueDeviceNameInGatewayRule;
use App\Rules\UniqueRegisterAddressInDeviceRule;
use Illuminate\Validation\Rule;

class ValidationService
{
    /**
     * Get validation rules for Gateway model.
     */
    public function getGatewayValidationRules(?int $excludeId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'ip_address' => [
                'required',
                'ip',
                new UniqueGatewayIpPortRule($excludeId),
            ],
            'port' => [
                'required',
                'integer',
                new PortRangeRule(),
            ],
            'unit_id' => [
                'nullable',
                'integer',
                'min:1',
                'max:255',
            ],
            'poll_interval' => [
                'required',
                'integer',
                'min:1',
                'max:3600',
            ],
            'is_active' => [
                'boolean',
            ],
        ];
    }

    /**
     * Get validation messages for Gateway model.
     */
    public function getGatewayValidationMessages(): array
    {
        return [
            'name.required' => 'Gateway name is required.',
            'name.string' => 'Gateway name must be text.',
            'name.max' => 'Gateway name cannot exceed 255 characters.',
            'ip_address.required' => 'IP address is required.',
            'ip_address.ip' => 'Please enter a valid IP address.',
            'port.required' => 'Port is required.',
            'port.integer' => 'Port must be a number.',
            'unit_id.integer' => 'Unit ID must be a number.',
            'unit_id.min' => 'Unit ID must be between 1 and 255.',
            'unit_id.max' => 'Unit ID must be between 1 and 255.',
            'poll_interval.required' => 'Poll interval is required.',
            'poll_interval.integer' => 'Poll interval must be a number.',
            'poll_interval.min' => 'Poll interval must be at least 1 second.',
            'poll_interval.max' => 'Poll interval cannot exceed 3600 seconds (1 hour).',
            'is_active.boolean' => 'Active status must be true or false.',
        ];
    }

    /**
     * Get validation rules for Device model.
     */
    public function getDeviceValidationRules(int $gatewayId, ?int $excludeId = null): array
    {
        return [
            'gateway_id' => [
                'required',
                'integer',
                Rule::exists('gateways', 'id'),
            ],
            'device_name' => [
                'required',
                'string',
                'max:255',
                new UniqueDeviceNameInGatewayRule($gatewayId, $excludeId),
            ],
            'device_type' => [
                'required',
                'string',
                Rule::in(array_keys(Device::DEVICE_TYPES)),
            ],
            'load_category' => [
                'required',
                'string',
                Rule::in(array_keys(Device::LOAD_CATEGORIES)),
            ],
            'enabled' => [
                'boolean',
            ],
        ];
    }

    /**
     * Get validation messages for Device model.
     */
    public function getDeviceValidationMessages(): array
    {
        return [
            'gateway_id.required' => 'Gateway selection is required.',
            'gateway_id.integer' => 'Gateway ID must be a number.',
            'gateway_id.exists' => 'Selected gateway does not exist.',
            'device_name.required' => 'Device name is required.',
            'device_name.string' => 'Device name must be text.',
            'device_name.max' => 'Device name cannot exceed 255 characters.',
            'device_type.required' => 'Device type is required.',
            'device_type.string' => 'Device type must be text.',
            'device_type.in' => 'Invalid device type selected.',
            'load_category.required' => 'Load category is required.',
            'load_category.string' => 'Load category must be text.',
            'load_category.in' => 'Invalid load category selected.',
            'enabled.boolean' => 'Enabled status must be true or false.',
        ];
    }

    /**
     * Get validation rules for Register model.
     */
    public function getRegisterValidationRules(int $deviceId, ?int $excludeId = null, ?string $dataType = null, ?int $count = null): array
    {
        $rules = [
            'device_id' => [
                'required',
                'integer',
                Rule::exists('devices', 'id'),
            ],
            'technical_label' => [
                'required',
                'string',
                'max:255',
            ],
            'function' => [
                'required',
                'integer',
                Rule::in([1, 2, 3, 4]),
            ],
            'register_address' => [
                'required',
                'integer',
                new ModbusAddressRule(),
                new UniqueRegisterAddressInDeviceRule($deviceId, $excludeId),
            ],
            'data_type' => [
                'required',
                'string',
                Rule::in(array_keys(Register::DATA_TYPES)),
            ],
            'byte_order' => [
                'required',
                'string',
                Rule::in(array_keys(Register::BYTE_ORDERS)),
            ],
            'scale' => [
                'nullable',
                'numeric',
                new ScaleFactorRule(),
            ],
            'count' => [
                'required',
                'integer',
                'min:1',
                'max:4',
            ],
            'enabled' => [
                'boolean',
            ],
        ];

        // Add dynamic validation for register address range and count
        if ($count !== null) {
            $rules['register_address'][] = new ModbusAddressRangeRule($count);
        }

        if ($dataType !== null) {
            $rules['count'][] = new RegisterCountForDataTypeRule($dataType);
        }

        return $rules;
    }

    /**
     * Get validation messages for Register model.
     */
    public function getRegisterValidationMessages(): array
    {
        return [
            'device_id.required' => 'Device selection is required.',
            'device_id.integer' => 'Device ID must be a number.',
            'device_id.exists' => 'Selected device does not exist.',
            'technical_label.required' => 'Register name is required.',
            'technical_label.string' => 'Register name must be text.',
            'technical_label.max' => 'Register name cannot exceed 255 characters.',
            'function.required' => 'Modbus function is required.',
            'function.integer' => 'Modbus function must be a number.',
            'function.in' => 'Invalid Modbus function. Must be 1, 2, 3, or 4.',
            'register_address.required' => 'Register address is required.',
            'register_address.integer' => 'Register address must be a number.',
            'data_type.required' => 'Data type is required.',
            'data_type.string' => 'Data type must be text.',
            'data_type.in' => 'Invalid data type selected.',
            'byte_order.required' => 'Byte order is required.',
            'byte_order.string' => 'Byte order must be text.',
            'byte_order.in' => 'Invalid byte order selected.',
            'scale.numeric' => 'Scale factor must be a number.',
            'count.required' => 'Register count is required.',
            'count.integer' => 'Register count must be a number.',
            'count.min' => 'Register count must be at least 1.',
            'count.max' => 'Register count cannot exceed 4.',
            'enabled.boolean' => 'Enabled status must be true or false.',
        ];
    }

    /**
     * Validate gateway data and return validation errors.
     */
    public function validateGateway(array $data, ?int $excludeId = null): array
    {
        $validator = validator($data, $this->getGatewayValidationRules($excludeId), $this->getGatewayValidationMessages());
        
        return $validator->errors()->toArray();
    }

    /**
     * Validate device data and return validation errors.
     */
    public function validateDevice(array $data, int $gatewayId, ?int $excludeId = null): array
    {
        $validator = validator($data, $this->getDeviceValidationRules($gatewayId, $excludeId), $this->getDeviceValidationMessages());
        
        return $validator->errors()->toArray();
    }

    /**
     * Validate register data and return validation errors.
     */
    public function validateRegister(array $data, int $deviceId, ?int $excludeId = null): array
    {
        $dataType = $data['data_type'] ?? null;
        $count = $data['count'] ?? null;
        
        $validator = validator(
            $data, 
            $this->getRegisterValidationRules($deviceId, $excludeId, $dataType, $count), 
            $this->getRegisterValidationMessages()
        );
        
        return $validator->errors()->toArray();
    }

    /**
     * Validate IP address format.
     */
    public function validateIpAddress(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Validate port range.
     */
    public function validatePort(int $port): bool
    {
        return $port >= 1 && $port <= 65535;
    }

    /**
     * Validate Modbus address range.
     */
    public function validateModbusAddress(int $address): bool
    {
        return $address >= 0 && $address <= 65535;
    }

    /**
     * Validate Modbus address range with count.
     */
    public function validateModbusAddressRange(int $startAddress, int $count): bool
    {
        $endAddress = $startAddress + $count - 1;
        return $this->validateModbusAddress($startAddress) && $endAddress <= 65535;
    }

    /**
     * Validate scale factor.
     */
    public function validateScaleFactor(float $scale): bool
    {
        return $scale > 0 && $scale <= 1000000;
    }

    /**
     * Get user-friendly error message for validation failures.
     */
    public function getValidationErrorMessage(string $field, string $rule, array $parameters = []): string
    {
        $messages = [
            'ip_address.ip' => 'Please enter a valid IP address.',
            'port.range' => 'Port must be between 1 and 65535.',
            'register_address.range' => 'Register address must be between 0 and 65535.',
            'register_address.unique' => 'A register with this address already exists for this device.',
            'device_name.unique' => 'A device with this name already exists in this gateway.',
            'gateway.unique' => 'A gateway with this IP address and port combination already exists.',
            'scale.range' => 'Scale factor must be between 0 and 1,000,000.',
            'count.insufficient' => 'Register count is insufficient for the selected data type.',
            'address_range.exceeded' => 'Register address range exceeds Modbus limit (65535).',
        ];

        $key = "{$field}.{$rule}";
        return $messages[$key] ?? 'Validation error occurred.';
    }

    /**
     * Check if gateway IP/port combination is unique.
     */
    public function isGatewayIpPortUnique(string $ipAddress, int $port, ?int $excludeId = null): bool
    {
        $query = Gateway::where('ip_address', $ipAddress)->where('port', $port);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    /**
     * Check if device name is unique within gateway.
     */
    public function isDeviceNameUniqueInGateway(string $deviceName, int $gatewayId, ?int $excludeId = null): bool
    {
        $query = Device::where('device_name', $deviceName)->where('gateway_id', $gatewayId);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    /**
     * Check if register address is unique within device.
     */
    public function isRegisterAddressUniqueInDevice(int $address, int $deviceId, ?int $excludeId = null): bool
    {
        $query = Register::where('register_address', $address)->where('device_id', $deviceId);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return !$query->exists();
    }
}