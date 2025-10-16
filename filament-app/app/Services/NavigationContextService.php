<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\Device;
use App\Models\Register;
use App\Filament\Resources\GatewayResource;
use App\Filament\Resources\GatewayResource\Pages\ManageGatewayDevices;
use App\Filament\Resources\GatewayResource\Pages\ManageDeviceRegisters;

class NavigationContextService
{
    /**
     * Generate breadcrumbs for the gateway hierarchy
     */
    public function generateBreadcrumbs(string $level, ?Gateway $gateway = null, ?Device $device = null): array
    {
        $breadcrumbs = [];
        
        // Always start with Gateways
        $breadcrumbs[GatewayResource::getUrl('index')] = 'Gateways';
        
        if ($level === 'devices' && $gateway) {
            $breadcrumbs['#'] = "Devices - {$gateway->name}";
        } elseif ($level === 'registers' && $gateway && $device) {
            $breadcrumbs[ManageGatewayDevices::getUrl(['record' => $gateway->id])] = "Devices - {$gateway->name}";
            $breadcrumbs['#'] = "Registers - {$device->device_name}";
        }
        
        return $breadcrumbs;
    }

    /**
     * Generate navigation context information
     */
    public function generateNavigationContext(string $level, ?Gateway $gateway = null, ?Device $device = null): array
    {
        $context = [
            'level' => $level,
            'timestamp' => now()->toISOString(),
        ];
        
        if ($gateway) {
            $context['gateway'] = [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'ip_address' => $gateway->ip_address,
                'port' => $gateway->port,
                'is_active' => $gateway->is_active,
                'device_count' => $gateway->devices()->count(),
                'register_count' => $gateway->devices()->withCount('registers')->get()->sum('registers_count'),
            ];
        }
        
        if ($device) {
            $context['device'] = [
                'id' => $device->id,
                'device_name' => $device->device_name,
                'device_type' => $device->device_type,
                'device_type_name' => $device->device_type_name,
                'load_category' => $device->load_category,
                'load_category_name' => $device->load_category_name,
                'enabled' => $device->enabled,
                'register_count' => $device->registers()->count(),
                'active_register_count' => $device->registers()->where('enabled', true)->count(),
            ];
        }
        
        return $context;
    }

    /**
     * Generate page title based on context
     */
    public function generatePageTitle(string $level, ?Gateway $gateway = null, ?Device $device = null): string
    {
        return match ($level) {
            'devices' => $gateway ? "Manage Devices - {$gateway->name}" : 'Manage Devices',
            'registers' => $device ? "Manage Registers - {$device->device_name}" : 'Manage Registers',
            default => 'Gateway Management',
        };
    }

    /**
     * Generate page subheading with context information
     */
    public function generatePageSubheading(string $level, ?Gateway $gateway = null, ?Device $device = null): ?string
    {
        if ($level === 'devices' && $gateway) {
            $deviceCount = $gateway->devices()->count();
            $registerCount = $gateway->devices()->withCount('registers')->get()->sum('registers_count');
            $activeDeviceCount = $gateway->devices()->where('enabled', true)->count();
            
            return "Gateway: {$gateway->ip_address}:{$gateway->port} | " .
                   "Devices: {$deviceCount} | " .
                   "Active: {$activeDeviceCount} | " .
                   "Total Registers: {$registerCount}";
        }
        
        if ($level === 'registers' && $gateway && $device) {
            $registerCount = $device->registers()->count();
            $activeRegisterCount = $device->registers()->where('enabled', true)->count();
            
            return "Gateway: {$gateway->name} ({$gateway->ip_address}:{$gateway->port}) | " .
                   "Device: {$device->device_name} ({$device->device_type_name}) | " .
                   "Registers: {$registerCount} | " .
                   "Active: {$activeRegisterCount}";
        }
        
        return null;
    }

    /**
     * Get navigation URLs for back buttons
     */
    public function getNavigationUrls(string $level, ?Gateway $gateway = null, ?Device $device = null): array
    {
        $urls = [];
        
        if ($level === 'devices' && $gateway) {
            $urls['parent'] = GatewayResource::getUrl('index');
            $urls['parent_label'] = 'Gateways';
        } elseif ($level === 'registers' && $gateway && $device) {
            $urls['parent'] = ManageGatewayDevices::getUrl(['record' => $gateway->id]);
            $urls['parent_label'] = "Devices - {$gateway->name}";
            $urls['grandparent'] = GatewayResource::getUrl('index');
            $urls['grandparent_label'] = 'Gateways';
        }
        
        return $urls;
    }

    /**
     * Generate status information for display
     */
    public function generateStatusInfo(?Gateway $gateway = null, ?Device $device = null): array
    {
        $status = [];
        
        if ($gateway) {
            $status['gateway_status'] = [
                'is_active' => $gateway->is_active,
                'last_seen' => $gateway->last_seen_at?->diffForHumans(),
                'connection_status' => $this->getGatewayConnectionStatus($gateway),
            ];
        }
        
        if ($device) {
            $status['device_status'] = [
                'enabled' => $device->enabled,
                'type' => $device->device_type_name,
                'category' => $device->load_category_name,
            ];
        }
        
        return $status;
    }

    /**
     * Determine gateway connection status
     */
    private function getGatewayConnectionStatus(Gateway $gateway): string
    {
        if (!$gateway->is_active) {
            return 'inactive';
        }
        
        if (!$gateway->last_seen_at) {
            return 'never_connected';
        }
        
        $threshold = now()->subSeconds($gateway->poll_interval * 3);
        
        if ($gateway->last_seen_at->greaterThan($threshold)) {
            return 'online';
        }
        
        return 'offline';
    }
}