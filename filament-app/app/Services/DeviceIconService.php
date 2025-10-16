<?php

namespace App\Services;

use App\Models\DataPoint;

class DeviceIconService
{
    /**
     * Get the appropriate icon for a device based on its load category and device type
     */
    public static function getDeviceIcon(DataPoint $dataPoint): string
    {
        // Use the model's built-in device_icon attribute if available
        if (isset($dataPoint->device_icon)) {
            return $dataPoint->device_icon;
        }
        
        // Fallback to load_category-based detection
        return match($dataPoint->load_category ?? 'other') {
            'mains' => '/images/icons/electric-meter.png',
            'ac' => '/images/icons/fan(1).png',
            'sockets' => '/images/icons/supply.png',
            'heater' => '/images/icons/radiator.png',
            'lighting' => '/images/icons/supply.png',
            'water' => '/images/icons/faucet(1).png',
            'solar' => '/images/icons/electric-meter.png',
            'generator' => '/images/icons/electric-meter.png',
            default => self::getLegacyIcon($dataPoint)
        };
    }
    
    /**
     * Legacy icon detection for backward compatibility
     */
    private static function getLegacyIcon(DataPoint $dataPoint): string
    {
        $label = strtolower($dataPoint->custom_label ?: $dataPoint->label ?: '');
        $groupName = strtolower($dataPoint->group_name ?? '');
        $searchText = $label . ' ' . $groupName;
        
        // Power/Electric meter keywords
        $powerKeywords = ['power', 'electric', 'energy', 'kwh', 'kw-h', 'kilowatt', 'watt', 'electricity', 'main supply', 'supply'];
        foreach ($powerKeywords as $keyword) {
            if (str_contains($searchText, $keyword)) {
                return '/images/icons/electric-meter.png';
            }
        }
        
        // Water meter keywords
        $waterKeywords = ['water', 'faucet', 'tap', 'flow', 'm³', 'm3', 'cubic', 'liter', 'litre'];
        foreach ($waterKeywords as $keyword) {
            if (str_contains($searchText, $keyword)) {
                return '/images/icons/faucet(1).png';
            }
        }
        
        // Heater/Radiator keywords
        $heaterKeywords = ['heater', 'radiator', 'heating', 'thermal', 'temperature', 'heat'];
        foreach ($heaterKeywords as $keyword) {
            if (str_contains($searchText, $keyword)) {
                return '/images/icons/radiator.png';
            }
        }
        
        // AC/Ventilation/Fan keywords
        $acKeywords = ['ac', 'air conditioning', 'ventilation', 'fan', 'cooling', 'hvac', 'climate'];
        foreach ($acKeywords as $keyword) {
            if (str_contains($searchText, $keyword)) {
                return '/images/icons/fan(1).png';
            }
        }
        
        // Default to electric meter for unknown devices
        return '/images/icons/electric-meter.png';
    }
    
    /**
     * Get device type name for display
     */
    public static function getDeviceType(DataPoint $dataPoint): string
    {
        // Use the model's built-in device_type_name attribute if available
        if (isset($dataPoint->device_type_name)) {
            return $dataPoint->device_type_name;
        }
        
        // Use load_category for more specific naming
        if (isset($dataPoint->load_category_name)) {
            return $dataPoint->load_category_name;
        }
        
        // Fallback to device_type
        return match($dataPoint->device_type ?? 'energy') {
            'energy' => 'Energy Meter',
            'water' => 'Water Meter', 
            'control' => 'Control Device',
            default => 'Device'
        };
    }
    
    /**
     * Get status color class based on device status
     */
    public static function getStatusColor(string $status): string
    {
        return match($status) {
            'online' => 'text-green-600 bg-green-100',
            'warning' => 'text-yellow-600 bg-yellow-100',
            'offline' => 'text-red-600 bg-red-100',
            default => 'text-gray-600 bg-gray-100'
        };
    }
    
    /**
     * Get status dot color
     */
    public static function getStatusDotColor(string $status): string
    {
        return match($status) {
            'online' => 'bg-green-400',
            'warning' => 'bg-yellow-400',
            'offline' => 'bg-red-400',
            default => 'bg-gray-400'
        };
    }
    
    /**
     * Get device-specific gradient classes
     */
    public static function getDeviceGradient(DataPoint $dataPoint): string
    {
        // Use load_category for more specific gradients
        return match($dataPoint->load_category ?? 'other') {
            'mains' => 'from-yellow-50 to-orange-100',
            'ac' => 'from-sky-50 to-blue-100',
            'sockets' => 'from-purple-50 to-indigo-100',
            'heater' => 'from-red-50 to-pink-100',
            'lighting' => 'from-amber-50 to-yellow-100',
            'water' => 'from-blue-50 to-cyan-100',
            'solar' => 'from-green-50 to-emerald-100',
            'generator' => 'from-orange-50 to-red-100',
            default => match($dataPoint->device_type ?? 'energy') {
                'energy' => 'from-yellow-50 to-orange-100',
                'water' => 'from-blue-50 to-cyan-100',
                'control' => 'from-purple-50 to-indigo-100',
                default => 'from-gray-50 to-slate-100'
            }
        };
    }
}