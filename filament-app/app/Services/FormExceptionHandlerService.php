<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class FormExceptionHandlerService
{
    /**
     * Handle exceptions from form submissions and provide user-friendly feedback.
     */
    public function handleFormException(\Exception $exception, string $context = 'form submission'): void
    {
        if ($exception instanceof ValidationException) {
            $this->handleValidationException($exception);
        } elseif ($exception instanceof QueryException) {
            $this->handleQueryException($exception, $context);
        } else {
            $this->handleGenericException($exception, $context);
        }
    }

    /**
     * Handle validation exceptions.
     */
    protected function handleValidationException(ValidationException $exception): void
    {
        $errors = $exception->errors();
        $firstError = collect($errors)->flatten()->first();
        
        Notification::make()
            ->title('Validation Error')
            ->body($firstError ?: 'Please check your input and try again.')
            ->danger()
            ->duration(8000)
            ->send();
    }

    /**
     * Handle database query exceptions.
     */
    protected function handleQueryException(QueryException $exception, string $context): void
    {
        $errorCode = $exception->getCode();
        $errorMessage = $exception->getMessage();
        
        // Log the full exception for debugging
        Log::error("Database error during {$context}", [
            'code' => $errorCode,
            'message' => $errorMessage,
            'sql' => $exception->getSql() ?? 'N/A',
            'bindings' => $exception->getBindings() ?? [],
            'trace' => $exception->getTraceAsString()
        ]);

        $userMessage = $this->getDatabaseErrorMessage($errorCode, $errorMessage);
        
        Notification::make()
            ->title('Database Error')
            ->body($userMessage)
            ->danger()
            ->duration(10000)
            ->send();
    }

    /**
     * Handle generic exceptions.
     */
    protected function handleGenericException(\Exception $exception, string $context): void
    {
        // Log the full exception for debugging
        Log::error("Unexpected error during {$context}", [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);

        Notification::make()
            ->title('Unexpected Error')
            ->body('An unexpected error occurred. Please try again or contact support if the problem persists.')
            ->danger()
            ->duration(10000)
            ->send();
    }

    /**
     * Get user-friendly message for database errors.
     */
    protected function getDatabaseErrorMessage(string $errorCode, string $errorMessage): string
    {
        // Handle specific database error codes
        if (str_contains($errorMessage, 'foreign key constraint')) {
            if (str_contains($errorMessage, 'gateway_id')) {
                return 'Invalid gateway selection. The selected gateway may have been deleted.';
            }
            if (str_contains($errorMessage, 'device_id')) {
                return 'Invalid device selection. The selected device may have been deleted.';
            }
            return 'Invalid reference to related data. Please refresh the page and try again.';
        }

        if (str_contains($errorMessage, 'unique constraint') || str_contains($errorMessage, 'duplicate entry')) {
            if (str_contains($errorMessage, 'ip_address') && str_contains($errorMessage, 'port')) {
                return 'A gateway with this IP address and port combination already exists.';
            }
            if (str_contains($errorMessage, 'device_name')) {
                return 'A device with this name already exists in this gateway.';
            }
            if (str_contains($errorMessage, 'register_address')) {
                return 'A register with this address already exists for this device.';
            }
            return 'This record already exists. Please use different values.';
        }

        if (str_contains($errorMessage, 'not null constraint')) {
            return 'Required information is missing. Please fill in all required fields.';
        }

        if (str_contains($errorMessage, 'check constraint')) {
            return 'Invalid data provided. Please check your input values.';
        }

        if (str_contains($errorMessage, 'connection') || str_contains($errorMessage, 'timeout')) {
            return 'Database connection error. Please try again in a moment.';
        }

        // Generic database error message
        return 'A database error occurred. Please check your input and try again.';
    }

    /**
     * Handle gateway-specific form exceptions.
     */
    public function handleGatewayFormException(\Exception $exception): void
    {
        $this->handleFormException($exception, 'gateway form submission');
    }

    /**
     * Handle device-specific form exceptions.
     */
    public function handleDeviceFormException(\Exception $exception): void
    {
        $this->handleFormException($exception, 'device form submission');
    }

    /**
     * Handle register-specific form exceptions.
     */
    public function handleRegisterFormException(\Exception $exception): void
    {
        $this->handleFormException($exception, 'register form submission');
    }

    /**
     * Handle connection test exceptions.
     */
    public function handleConnectionTestException(\Exception $exception): void
    {
        Log::error('Gateway connection test failed', [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $message = 'Connection test failed. ';
        
        if (str_contains($exception->getMessage(), 'timeout')) {
            $message .= 'The gateway did not respond within the timeout period. Check the IP address and port.';
        } elseif (str_contains($exception->getMessage(), 'connection refused')) {
            $message .= 'Connection was refused. Check if the gateway is running and the port is correct.';
        } elseif (str_contains($exception->getMessage(), 'host unreachable')) {
            $message .= 'The gateway host is unreachable. Check the IP address and network connectivity.';
        } else {
            $message .= 'Please check the gateway configuration and network connectivity.';
        }

        Notification::make()
            ->title('Connection Test Failed')
            ->body($message)
            ->danger()
            ->duration(10000)
            ->send();
    }

    /**
     * Handle bulk operation exceptions.
     */
    public function handleBulkOperationException(\Exception $exception, string $operation): void
    {
        Log::error("Bulk operation '{$operation}' failed", [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        Notification::make()
            ->title('Bulk Operation Failed')
            ->body("The {$operation} operation could not be completed. Some items may have been processed successfully.")
            ->danger()
            ->duration(8000)
            ->send();
    }

    /**
     * Show success notification with validation warnings if any.
     */
    public function showSuccessWithWarnings(string $title, string $message, array $warnings = []): void
    {
        $body = $message;
        
        if (!empty($warnings)) {
            $body .= "\n\nWarnings:\n" . implode("\n", array_slice($warnings, 0, 3));
            if (count($warnings) > 3) {
                $body .= "\n... and " . (count($warnings) - 3) . " more warnings.";
            }
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->color(empty($warnings) ? 'success' : 'warning')
            ->duration(empty($warnings) ? 5000 : 8000)
            ->send();
    }

    /**
     * Validate and show real-time feedback for form fields.
     */
    public function validateFieldRealTime(string $field, mixed $value, array $rules): ?string
    {
        try {
            $validator = validator([$field => $value], [$field => $rules]);
            
            if ($validator->fails()) {
                return $validator->errors()->first($field);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning("Real-time validation failed for field {$field}", [
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            
            return null; // Don't show errors for real-time validation failures
        }
    }
}