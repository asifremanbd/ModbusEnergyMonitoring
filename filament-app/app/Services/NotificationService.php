<?php

namespace App\Services;

use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Illuminate\Support\Facades\Session;

class NotificationService
{
    /**
     * Show success notification with optional undo functionality
     */
    public function success(string $message, ?callable $undoAction = null, ?string $undoLabel = 'Undo'): void
    {
        $notification = Notification::make()
            ->title('Success')
            ->body($message)
            ->success()
            ->duration(5000);
        
        if ($undoAction) {
            $notification->actions([
                Action::make('undo')
                    ->label($undoLabel)
                    ->button()
                    ->color('gray')
                    ->action($undoAction),
            ]);
        }
        
        $notification->send();
    }
    
    /**
     * Show error notification with diagnostic information
     */
    public function error(string $message, ?array $diagnosticInfo = null, bool $persistent = false): void
    {
        $notification = Notification::make()
            ->title('Error')
            ->body($message)
            ->danger();
        
        if ($persistent) {
            $notification->persistent();
        } else {
            $notification->duration(8000);
        }
        
        if ($diagnosticInfo) {
            $notification->actions([
                Action::make('view_details')
                    ->label('View Details')
                    ->button()
                    ->color('gray')
                    ->action(function () use ($diagnosticInfo) {
                        Session::flash('diagnostic_info', $diagnosticInfo);
                        // This would trigger a modal or redirect to diagnostics page
                    }),
            ]);
        }
        
        $notification->send();
    }
    
    /**
     * Show warning notification
     */
    public function warning(string $message, ?array $actions = null): void
    {
        $notification = Notification::make()
            ->title('Warning')
            ->body($message)
            ->warning()
            ->duration(6000);
        
        if ($actions) {
            $notification->actions($actions);
        }
        
        $notification->send();
    }
    
    /**
     * Show info notification
     */
    public function info(string $message, ?array $actions = null): void
    {
        $notification = Notification::make()
            ->title('Information')
            ->body($message)
            ->info()
            ->duration(4000);
        
        if ($actions) {
            $notification->actions($actions);
        }
        
        $notification->send();
    }
    
    /**
     * Show gateway connection test result
     */
    public function connectionTest(bool $success, float $latency, ?int $testValue = null, ?string $error = null): void
    {
        if ($success) {
            $message = "Connection successful! Latency: {$latency}ms";
            if ($testValue !== null) {
                $message .= ", Test register value: {$testValue}";
            }
            
            $this->success($message);
        } else {
            $this->error("Connection failed: " . ($error ?? 'Unknown error'), null, false);
        }
    }
    
    /**
     * Show gateway operation result (pause/resume/delete)
     */
    public function gatewayOperation(string $operation, string $gatewayName, bool $success, ?callable $undoAction = null): void
    {
        if ($success) {
            $message = match ($operation) {
                'pause' => "Modbus registration '{$gatewayName}' has been paused. Polling will stop until resumed.",
                'resume' => "Modbus registration '{$gatewayName}' has been resumed. Polling will start shortly.",
                'delete' => "Modbus registration '{$gatewayName}' and all its data have been deleted.",
                'create' => "Modbus registration '{$gatewayName}' has been created successfully.",
                'update' => "Modbus registration '{$gatewayName}' configuration has been updated.",
                default => "Operation completed for Modbus registration '{$gatewayName}'.",
            };
            
            $this->success($message, $undoAction);
        } else {
            $message = "Failed to {$operation} Modbus registration '{$gatewayName}'. Please try again.";
            $this->error($message);
        }
    }
    
    /**
     * Show bulk operation result
     */
    public function bulkOperation(string $operation, int $successCount, int $totalCount): void
    {
        if ($successCount === $totalCount) {
            $message = match ($operation) {
                'pause' => "Successfully paused {$successCount} registrations.",
                'resume' => "Successfully resumed {$successCount} registrations.",
                'delete' => "Successfully deleted {$successCount} registrations.",
                'enable' => "Successfully enabled {$successCount} data points.",
                'disable' => "Successfully disabled {$successCount} data points.",
                default => "Successfully processed {$successCount} items.",
            };
            
            $this->success($message);
        } elseif ($successCount > 0) {
            $message = "Partially completed: {$successCount} of {$totalCount} items processed successfully.";
            $this->warning($message);
        } else {
            $message = "Operation failed: No items were processed successfully.";
            $this->error($message);
        }
    }
    
    /**
     * Show data point operation result
     */
    public function dataPointOperation(string $operation, string $pointLabel, bool $success): void
    {
        if ($success) {
            $message = match ($operation) {
                'create' => "Data point '{$pointLabel}' has been created.",
                'update' => "Data point '{$pointLabel}' has been updated.",
                'delete' => "Data point '{$pointLabel}' has been deleted.",
                'enable' => "Data point '{$pointLabel}' has been enabled.",
                'disable' => "Data point '{$pointLabel}' has been disabled.",
                'test' => "Data point '{$pointLabel}' test read completed successfully.",
                default => "Operation completed for data point '{$pointLabel}'.",
            };
            
            $this->success($message);
        } else {
            $message = "Failed to {$operation} data point '{$pointLabel}'. Please check the configuration.";
            $this->error($message);
        }
    }
    
    /**
     * Show template operation result
     */
    public function templateOperation(string $operation, string $templateName, int $pointsCount): void
    {
        $message = match ($operation) {
            'apply' => "Applied '{$templateName}' template successfully. Created {$pointsCount} data points.",
            'export' => "Exported {$pointsCount} data points to CSV format.",
            'import' => "Imported {$pointsCount} data points from template.",
            default => "Template operation completed: {$pointsCount} points processed.",
        };
        
        $this->success($message);
    }
    
    /**
     * Show validation error notification
     */
    public function validationError(array $errors): void
    {
        $message = "Please correct the following errors:\n";
        foreach ($errors as $field => $fieldErrors) {
            $message .= "• " . implode(', ', $fieldErrors) . "\n";
        }
        
        $this->error(trim($message));
    }
    
    /**
     * Show system status notification
     */
    public function systemStatus(string $status, array $details = []): void
    {
        $message = match ($status) {
            'polling_started' => 'Gateway polling system has been started.',
            'polling_stopped' => 'Gateway polling system has been stopped.',
            'polling_restarted' => 'Gateway polling system has been restarted.',
            'high_failure_rate' => 'High failure rate detected across multiple gateways.',
            'system_healthy' => 'All systems are operating normally.',
            default => "System status: {$status}",
        };
        
        if (!empty($details)) {
            $message .= ' ' . implode(', ', $details);
        }
        
        match ($status) {
            'high_failure_rate' => $this->warning($message),
            'system_healthy' => $this->success($message),
            default => $this->info($message),
        };
    }
}