<?php

namespace App\Services;

use App\Models\DataPoint;
use App\Models\Gateway;
use InvalidArgumentException;

class DataPointMappingService
{
    /**
     * Supported Modbus data types with their properties.
     */
    public const DATA_TYPES = [
        'int16' => ['size' => 1, 'signed' => true],
        'uint16' => ['size' => 1, 'signed' => false],
        'int32' => ['size' => 2, 'signed' => true],
        'uint32' => ['size' => 2, 'signed' => false],
        'float32' => ['size' => 2, 'signed' => true],
        'float64' => ['size' => 4, 'signed' => true],
    ];

    /**
     * Supported byte order configurations.
     */
    public const BYTE_ORDERS = [
        'big_endian',
        'little_endian',
        'word_swapped',
    ];

    /**
     * Default configuration values.
     */
    public const DEFAULTS = [
        'port' => 502,
        'unit_id' => 1,
        'poll_interval' => 10,
        'modbus_function' => 4, // Input registers
        'register_count' => 2,
        'data_type' => 'float32',
        'byte_order' => 'word_swapped',
        'scale_factor' => 1.0,
        'is_enabled' => true,
    ];

    /**
     * Create a new data point with validation.
     */
    public function createDataPoint(Gateway $gateway, array $config): DataPoint
    {
        $validatedConfig = $this->validateDataPointConfig($config);
        
        return DataPoint::create([
            'gateway_id' => $gateway->id,
            'group_name' => $validatedConfig['group_name'],
            'label' => $validatedConfig['label'],
            'modbus_function' => $validatedConfig['modbus_function'],
            'register_address' => $validatedConfig['register_address'],
            'register_count' => $validatedConfig['register_count'],
            'data_type' => $validatedConfig['data_type'],
            'byte_order' => $validatedConfig['byte_order'],
            'scale_factor' => $validatedConfig['scale_factor'],
            'is_enabled' => $validatedConfig['is_enabled'] ?? self::DEFAULTS['is_enabled'],
        ]);
    }

    /**
     * Validate data point configuration.
     */
    public function validateDataPointConfig(array $config): array
    {
        // Required fields
        $required = ['group_name', 'label', 'register_address'];
        foreach ($required as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                throw new InvalidArgumentException("Field '{$field}' is required");
            }
        }

        // Validate register address range
        if ($config['register_address'] < 1 || $config['register_address'] > 65535) {
            throw new InvalidArgumentException('Register address must be between 1 and 65535');
        }

        // Validate Modbus function
        $validFunctions = [3, 4]; // Holding and Input registers
        $modbusFunction = $config['modbus_function'] ?? self::DEFAULTS['modbus_function'];
        if (!in_array($modbusFunction, $validFunctions)) {
            throw new InvalidArgumentException('Modbus function must be 3 (Holding) or 4 (Input)');
        }

        // Validate data type
        $dataType = $config['data_type'] ?? self::DEFAULTS['data_type'];
        if (!array_key_exists($dataType, self::DATA_TYPES)) {
            throw new InvalidArgumentException('Invalid data type: ' . $dataType);
        }

        // Validate byte order
        $byteOrder = $config['byte_order'] ?? self::DEFAULTS['byte_order'];
        if (!in_array($byteOrder, self::BYTE_ORDERS)) {
            throw new InvalidArgumentException('Invalid byte order: ' . $byteOrder);
        }

        // Auto-calculate register count based on data type if not provided
        $registerCount = $config['register_count'] ?? self::DATA_TYPES[$dataType]['size'];
        if ($registerCount < 1 || $registerCount > 4) {
            throw new InvalidArgumentException('Register count must be between 1 and 4');
        }

        // Validate scale factor
        $scaleFactor = $config['scale_factor'] ?? self::DEFAULTS['scale_factor'];
        if (!is_numeric($scaleFactor) || $scaleFactor == 0) {
            throw new InvalidArgumentException('Scale factor must be a non-zero number');
        }

        return [
            'group_name' => $config['group_name'],
            'label' => $config['label'],
            'modbus_function' => $modbusFunction,
            'register_address' => (int) $config['register_address'],
            'register_count' => (int) $registerCount,
            'data_type' => $dataType,
            'byte_order' => $byteOrder,
            'scale_factor' => (float) $scaleFactor,
            'is_enabled' => $config['is_enabled'] ?? self::DEFAULTS['is_enabled'],
        ];
    }

    /**
     * Check for register address conflicts within a gateway.
     */
    public function checkRegisterConflicts(Gateway $gateway, int $startAddress, int $count, ?int $excludeDataPointId = null): array
    {
        $endAddress = $startAddress + $count - 1;
        
        $query = $gateway->dataPoints()
            ->where(function ($q) use ($startAddress, $endAddress) {
                $q->whereBetween('register_address', [$startAddress, $endAddress])
                  ->orWhere(function ($q2) use ($startAddress, $endAddress) {
                      $q2->whereRaw('register_address + register_count - 1 >= ?', [$startAddress])
                         ->whereRaw('register_address + register_count - 1 <= ?', [$endAddress]);
                  })
                  ->orWhere(function ($q3) use ($startAddress, $endAddress) {
                      $q3->where('register_address', '<=', $startAddress)
                         ->whereRaw('register_address + register_count - 1 >= ?', [$endAddress]);
                  });
            });

        if ($excludeDataPointId) {
            $query->where('id', '!=', $excludeDataPointId);
        }

        return $query->get()->toArray();
    }

    /**
     * Get register range for a data point.
     */
    public function getRegisterRange(DataPoint $dataPoint): array
    {
        return [
            'start' => $dataPoint->register_address,
            'end' => $dataPoint->register_address + $dataPoint->register_count - 1,
            'count' => $dataPoint->register_count,
        ];
    }
}