<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\DataPoint;
use Exception;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class ErrorHandlingService
{
    /**
     * Handle Modbus communication errors with user-friendly messages
     */
    public function handleModbusError(Exception $exception, Gateway $gateway, ?DataPoint $dataPoint = null): array
    {
        $errorType = $this->categorizeModbusError($exception);
        $userMessage = $this->generateUserFriendlyMessage($errorType, $gateway, $dataPoint);
        $diagnosticInfo = $this->generateDiagnosticInfo($exception, $gateway, $dataPoint);
        
        // Log the detailed error for debugging
        Log::error('Modbus communication error', [
            'gateway_id' => $gateway->id,
            'gateway_name' => $gateway->name,
            'data_point_id' => $dataPoint?->id,
            'error_type' => $errorType,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
        
        return [
            'type' => $errorType,
            'user_message' => $userMessage,
            'diagnostic_info' => $diagnosticInfo,
            'severity' => $this->getErrorSeverity($errorType),
            'suggested_actions' => $this->getSuggestedActions($errorType, $gateway),
        ];
    }
    
    /**
     * Categorize Modbus errors into user-friendly types
     */
    private function categorizeModbusError(Exception $exception): string
    {
        $message = strtolower($exception->getMessage());
        
        // Check for connection timeout first (most specific)
        if (str_contains($message, 'timeout')) {
            return 'connection_timeout';
        }
        
        // Check for connection refused
        if (str_contains($message, 'refused') || str_contains($message, 'unreachable')) {
            return 'connection_refused';
        }
        
        // Check for register-related errors
        if (str_contains($message, 'illegal') && str_contains($message, 'register')) {
            return 'invalid_register';
        }
        
        // Check for function code errors
        if (str_contains($message, 'function') && (str_contains($message, 'code') || str_contains($message, 'unsupported'))) {
            return 'unsupported_function';
        }
        
        // Check for data decode errors
        if (str_contains($message, 'decode') || str_contains($message, 'parse') || str_contains($message, 'failed to decode')) {
            return 'data_decode_error';
        }
        
        // Check for insufficient registers
        if (str_contains($message, 'insufficient') && str_contains($message, 'registers')) {
            return 'insufficient_registers';
        }
        
        // Check for general connection issues
        if (str_contains($message, 'connection')) {
            return 'connection_timeout';
        }
        
        return 'unknown_error';
    }
    
    /**
     * Generate user-friendly error messages
     */
    private function generateUserFriendlyMessage(string $errorType, Gateway $gateway, ?DataPoint $dataPoint = null): string
    {
        $gatewayInfo = "{$gateway->name} ({$gateway->ip_address}:{$gateway->port})";
        $pointInfo = $dataPoint ? " for data point '{$dataPoint->label}'" : '';
        
        return match ($errorType) {
            'connection_timeout' => "Connection to gateway {$gatewayInfo} timed out{$pointInfo}. The device may be offline or network connectivity issues exist.",
            
            'connection_refused' => "Connection to gateway {$gatewayInfo} was refused{$pointInfo}. The device may not be running Modbus service on the specified port.",
            
            'invalid_register' => "Invalid register address{$pointInfo} on gateway {$gatewayInfo}. The requested register may not exist on this device.",
            
            'unsupported_function' => "Unsupported Modbus function{$pointInfo} on gateway {$gatewayInfo}. The device may not support the requested operation.",
            
            'data_decode_error' => "Failed to decode data{$pointInfo} from gateway {$gatewayInfo}. The data type configuration may be incorrect.",
            
            'insufficient_registers' => "Insufficient register data{$pointInfo} from gateway {$gatewayInfo}. The register count may be too small for the data type.",
            
            default => "An unexpected error occurred while communicating with gateway {$gatewayInfo}{$pointInfo}.",
        };
    }
    
    /**
     * Generate diagnostic information for troubleshooting
     */
    private function generateDiagnosticInfo(Exception $exception, Gateway $gateway, ?DataPoint $dataPoint = null): array
    {
        $info = [
            'gateway_config' => [
                'ip_address' => $gateway->ip_address,
                'port' => $gateway->port,
                'unit_id' => $gateway->unit_id,
                'poll_interval' => $gateway->poll_interval,
                'is_active' => $gateway->is_active,
            ],
            'error_details' => [
                'message' => $exception->getMessage(),
                'file' => basename($exception->getFile()),
                'line' => $exception->getLine(),
                'timestamp' => now()->toISOString(),
            ],
            'network_info' => [
                'last_seen' => $gateway->last_seen_at?->toISOString(),
                'success_count' => $gateway->success_count,
                'failure_count' => $gateway->failure_count,
                'success_rate' => $gateway->success_rate,
            ],
        ];
        
        if ($dataPoint) {
            $info['data_point_config'] = [
                'label' => $dataPoint->label,
                'group' => $dataPoint->group_name,
                'function' => $dataPoint->modbus_function,
                'register' => $dataPoint->register_address,
                'count' => $dataPoint->register_count,
                'data_type' => $dataPoint->data_type,
                'byte_order' => $dataPoint->byte_order,
            ];
        }
        
        return $info;
    }
    
    /**
     * Get error severity level
     */
    private function getErrorSeverity(string $errorType): string
    {
        return match ($errorType) {
            'connection_timeout', 'connection_refused' => 'high',
            'invalid_register', 'unsupported_function' => 'medium',
            'data_decode_error', 'insufficient_registers' => 'low',
            default => 'medium',
        };
    }
    
    /**
     * Get suggested actions for error resolution
     */
    private function getSuggestedActions(string $errorType, Gateway $gateway): array
    {
        return match ($errorType) {
            'connection_timeout' => [
                'Check network connectivity to the device',
                'Verify the IP address and port are correct',
                'Increase the connection timeout if the network is slow',
                'Check if the device is powered on and responding',
            ],
            
            'connection_refused' => [
                'Verify the Modbus TCP service is running on the device',
                'Check if the port number is correct (default: 502)',
                'Ensure no firewall is blocking the connection',
                'Verify the device supports Modbus TCP protocol',
            ],
            
            'invalid_register' => [
                'Check the device documentation for valid register addresses',
                'Verify the register address is within the supported range',
                'Ensure the Modbus function code is appropriate for the register',
            ],
            
            'unsupported_function' => [
                'Check if the device supports the requested Modbus function',
                'Try using function 3 (Holding Registers) instead of 4 (Input Registers) or vice versa',
                'Consult the device manual for supported functions',
            ],
            
            'data_decode_error' => [
                'Verify the data type configuration matches the device specification',
                'Check if the byte order (endianness) is correct',
                'Ensure the register count matches the data type requirements',
            ],
            
            'insufficient_registers' => [
                'Increase the register count to match the data type requirements',
                'Verify the starting register address allows for the required count',
                'Check device documentation for register layout',
            ],
            
            default => [
                'Check the device manual for troubleshooting information',
                'Verify all configuration parameters are correct',
                'Contact technical support if the issue persists',
            ],
        };
    }
    
    /**
     * Create error notification with diagnostic information
     */
    public function createErrorNotification(array $errorInfo, ?string $title = null): Notification
    {
        $notification = Notification::make()
            ->title($title ?? 'Communication Error')
            ->body($errorInfo['user_message'])
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('view_details')
                    ->label('View Details')
                    ->button()
                    ->color('gray')
                    ->action(function () use ($errorInfo) {
                        // This would open a modal with diagnostic information
                        session()->flash('error_diagnostic', $errorInfo);
                    }),
                \Filament\Notifications\Actions\Action::make('dismiss')
                    ->label('Dismiss')
                    ->close(),
            ]);
        
        return match ($errorInfo['severity']) {
            'high' => $notification->danger(),
            'medium' => $notification->warning(),
            'low' => $notification->info(),
            default => $notification->warning(),
        };
    }
    
    /**
     * Create success notification with undo functionality
     */
    public function createSuccessNotification(string $message, ?callable $undoAction = null): Notification
    {
        $notification = Notification::make()
            ->title('Success')
            ->body($message)
            ->success()
            ->duration(5000);
        
        if ($undoAction) {
            $notification->actions([
                \Filament\Notifications\Actions\Action::make('undo')
                    ->label('Undo')
                    ->button()
                    ->color('gray')
                    ->action($undoAction),
            ]);
        }
        
        return $notification;
    }
    
    /**
     * Get empty state message for different contexts
     */
    public function getEmptyStateMessage(string $context, array $params = []): array
    {
        return match ($context) {
            'no_gateways' => [
                'title' => 'No Gateways Configured',
                'message' => 'Get started by adding your first Teltonika gateway to begin monitoring energy data.',
                'action_label' => 'Add Gateway',
                'action_url' => route('filament.admin.resources.gateways.create'),
                'icon' => 'heroicon-o-server',
            ],
            
            'no_data_points' => [
                'title' => 'No Data Points Configured',
                'message' => 'Add measurement points to start collecting data from your gateway. You can use our Teltonika template to get started quickly.',
                'action_label' => 'Configure Points',
                'action_url' => $params['gateway_url'] ?? '#',
                'icon' => 'heroicon-o-chart-bar',
            ],
            
            'no_readings' => [
                'title' => 'No Recent Data',
                'message' => 'No readings have been collected yet. Make sure your gateways are online and polling is enabled.',
                'action_label' => 'Check Gateways',
                'action_url' => route('filament.admin.resources.gateways.index'),
                'icon' => 'heroicon-o-signal-slash',
            ],
            
            'gateway_offline' => [
                'title' => 'Gateway Offline',
                'message' => "Gateway '{$params['gateway_name']}' is currently offline. Check the connection and try again.",
                'action_label' => 'Test Connection',
                'action_url' => $params['test_url'] ?? '#',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            
            'high_failure_rate' => [
                'title' => 'High Failure Rate Detected',
                'message' => "Gateway '{$params['gateway_name']}' has a high failure rate ({$params['failure_rate']}%). Check the configuration and network connectivity.",
                'action_label' => 'View Diagnostics',
                'action_url' => $params['diagnostics_url'] ?? '#',
                'icon' => 'heroicon-o-exclamation-circle',
            ],
            
            default => [
                'title' => 'No Data Available',
                'message' => 'There is no data to display at this time.',
                'action_label' => 'Refresh',
                'action_url' => '#',
                'icon' => 'heroicon-o-information-circle',
            ],
        };
    }
}