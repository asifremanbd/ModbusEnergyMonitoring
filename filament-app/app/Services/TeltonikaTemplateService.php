<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\DataPoint;

class TeltonikaTemplateService
{
    /**
     * Predefined Teltonika templates.
     */
    public const TEMPLATES = [
        'teltonika_energy_meter' => [
            'name' => 'Teltonika Energy Meter (Standard)',
            'description' => 'Standard configuration for Teltonika energy meters with float32 values',
            'data_points' => [
                [
                    'group_name' => 'Meter_1',
                    'label' => 'Voltage L1',
                    'modbus_function' => 4,
                    'register_address' => 1,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Meter_1',
                    'label' => 'Voltage L2',
                    'modbus_function' => 4,
                    'register_address' => 3,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Meter_1',
                    'label' => 'Voltage L3',
                    'modbus_function' => 4,
                    'register_address' => 5,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Meter_1',
                    'label' => 'Current L1',
                    'modbus_function' => 4,
                    'register_address' => 7,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Meter_1',
                    'label' => 'Current L2',
                    'modbus_function' => 4,
                    'register_address' => 9,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Meter_1',
                    'label' => 'Current L3',
                    'modbus_function' => 4,
                    'register_address' => 11,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Meter_1',
                    'label' => 'Active Power Total',
                    'modbus_function' => 4,
                    'register_address' => 13,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1000.0, // Convert kW to W
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Meter_1',
                    'label' => 'Reactive Power Total',
                    'modbus_function' => 4,
                    'register_address' => 15,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1000.0, // Convert kVAR to VAR
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Meter_1',
                    'label' => 'Power Factor Total',
                    'modbus_function' => 4,
                    'register_address' => 17,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Meter_1',
                    'label' => 'Frequency',
                    'modbus_function' => 4,
                    'register_address' => 19,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Meter_1',
                    'label' => 'Energy Import Total',
                    'modbus_function' => 4,
                    'register_address' => 21,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1000.0, // Convert kWh to Wh
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Meter_1',
                    'label' => 'Energy Export Total',
                    'modbus_function' => 4,
                    'register_address' => 23,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1000.0, // Convert kWh to Wh
                    'is_enabled' => true,
                ],
            ],
        ],
        'teltonika_basic' => [
            'name' => 'Teltonika Basic (4 Points)',
            'description' => 'Basic configuration with essential measurements',
            'data_points' => [
                [
                    'group_name' => 'Basic',
                    'label' => 'Voltage',
                    'modbus_function' => 4,
                    'register_address' => 1,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Basic',
                    'label' => 'Current',
                    'modbus_function' => 4,
                    'register_address' => 3,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Basic',
                    'label' => 'Power',
                    'modbus_function' => 4,
                    'register_address' => 5,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Basic',
                    'label' => 'Energy',
                    'modbus_function' => 4,
                    'register_address' => 7,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
            ],
        ],
        'teltonika_kwh_only' => [
            'name' => 'Teltonika kWh Only (4 Meters)',
            'description' => 'Only Total kWh registers for 4 meters',
            'data_points' => [
                [
                    'group_name' => 'Meter_1',
                    'label' => 'Total_kWh',
                    'modbus_function' => 3,
                    'register_address' => 1025,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Meter_2',
                    'label' => 'Total_kWh',
                    'modbus_function' => 3,
                    'register_address' => 1033,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Meter_3',
                    'label' => 'Total_kWh',
                    'modbus_function' => 3,
                    'register_address' => 1035,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
                [
                    'group_name' => 'Meter_4',
                    'label' => 'Total_kWh',
                    'modbus_function' => 3,
                    'register_address' => 1037,
                    'register_count' => 2,
                    'data_type' => 'float32',
                    'byte_order' => 'word_swapped',
                    'scale_factor' => 1.0,
                    'is_enabled' => true,
                ],
            ],
        ],
    ];

    private DataPointMappingService $mappingService;

    public function __construct(DataPointMappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

    /**
     * Get all available templates.
     */
    public function getAvailableTemplates(): array
    {
        return array_map(function ($template, $key) {
            return [
                'key' => $key,
                'name' => $template['name'],
                'description' => $template['description'],
                'point_count' => count($template['data_points']),
            ];
        }, self::TEMPLATES, array_keys(self::TEMPLATES));
    }

    /**
     * Get template configuration by key.
     */
    public function getTemplate(string $templateKey): ?array
    {
        return self::TEMPLATES[$templateKey] ?? null;
    }

    /**
     * Get default template data points for the wizard.
     */
    public function getDefaultTemplate(): array
    {
        $template = self::TEMPLATES['teltonika_basic'];
        return $template['data_points'];
    }

    /**
     * Apply template to a gateway.
     */
    public function applyTemplate(Gateway $gateway, string $templateKey): array
    {
        $template = $this->getTemplate($templateKey);
        if (!$template) {
            throw new \InvalidArgumentException("Template '{$templateKey}' not found");
        }

        $createdDataPoints = [];
        
        foreach ($template['data_points'] as $pointConfig) {
            try {
                $dataPoint = $this->mappingService->createDataPoint($gateway, $pointConfig);
                $createdDataPoints[] = $dataPoint;
            } catch (\Exception $e) {
                // If any point fails, rollback created points
                foreach ($createdDataPoints as $createdPoint) {
                    $createdPoint->delete();
                }
                throw $e;
            }
        }

        return $createdDataPoints;
    }

    /**
     * Clone a group configuration to a new group.
     */
    public function cloneGroup(Gateway $gateway, string $sourceGroup, string $targetGroup, int $registerOffset = 0): array
    {
        $sourcePoints = $gateway->dataPoints()
            ->where('group_name', $sourceGroup)
            ->get();

        if ($sourcePoints->isEmpty()) {
            throw new \InvalidArgumentException("Source group '{$sourceGroup}' not found or empty");
        }

        $clonedPoints = [];

        foreach ($sourcePoints as $sourcePoint) {
            $config = [
                'group_name' => $targetGroup,
                'label' => str_replace($sourceGroup, $targetGroup, $sourcePoint->label),
                'modbus_function' => $sourcePoint->modbus_function,
                'register_address' => $sourcePoint->register_address + $registerOffset,
                'register_count' => $sourcePoint->register_count,
                'data_type' => $sourcePoint->data_type,
                'byte_order' => $sourcePoint->byte_order,
                'scale_factor' => $sourcePoint->scale_factor,
                'is_enabled' => $sourcePoint->is_enabled,
            ];

            try {
                $clonedPoint = $this->mappingService->createDataPoint($gateway, $config);
                $clonedPoints[] = $clonedPoint;
            } catch (\Exception $e) {
                // Rollback on error
                foreach ($clonedPoints as $clonedPoint) {
                    $clonedPoint->delete();
                }
                throw $e;
            }
        }

        return $clonedPoints;
    }

    /**
     * Get suggested register offset for cloning groups.
     */
    public function getSuggestedRegisterOffset(Gateway $gateway, string $sourceGroup): int
    {
        $sourcePoints = $gateway->dataPoints()
            ->where('group_name', $sourceGroup)
            ->get();

        if ($sourcePoints->isEmpty()) {
            return 0;
        }

        // Find the highest register address + count in the source group
        $maxRegister = $sourcePoints->max(function ($point) {
            return $point->register_address + $point->register_count - 1;
        });

        // Find the lowest register address in the source group
        $minRegister = $sourcePoints->min('register_address');

        // Calculate the range size
        $rangeSize = $maxRegister - $minRegister + 1;

        // Round up to nearest 10 for clean offset
        return (int) (ceil($rangeSize / 10) * 10);
    }

    /**
     * Validate template before applying.
     */
    public function validateTemplate(Gateway $gateway, string $templateKey): array
    {
        $template = $this->getTemplate($templateKey);
        if (!$template) {
            throw new \InvalidArgumentException("Template '{$templateKey}' not found");
        }

        $conflicts = [];
        
        foreach ($template['data_points'] as $index => $pointConfig) {
            try {
                // Validate the configuration
                $this->mappingService->validateDataPointConfig($pointConfig);
                
                // Check for register conflicts
                $registerConflicts = $this->mappingService->checkRegisterConflicts(
                    $gateway,
                    $pointConfig['register_address'],
                    $pointConfig['register_count']
                );
                
                if (!empty($registerConflicts)) {
                    $conflicts[] = [
                        'point_index' => $index,
                        'point_label' => $pointConfig['label'],
                        'conflicts' => $registerConflicts,
                    ];
                }
            } catch (\Exception $e) {
                $conflicts[] = [
                    'point_index' => $index,
                    'point_label' => $pointConfig['label'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $conflicts;
    }
}