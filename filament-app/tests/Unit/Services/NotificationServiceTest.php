<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\NotificationService;
use Filament\Notifications\Notification;

class NotificationServiceTest extends TestCase
{
    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = new NotificationService();
    }

    public function test_creates_success_notification()
    {
        // Mock the Notification facade
        Notification::fake();
        
        $this->notificationService->success('Operation completed successfully');
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Success' &&
                   $notification->getBody() === 'Operation completed successfully' &&
                   $notification->getDuration() === 5000;
        });
    }

    public function test_creates_success_notification_with_undo()
    {
        Notification::fake();
        
        $undoAction = function () {
            return 'undone';
        };
        
        $this->notificationService->success('Gateway paused', $undoAction, 'Undo Pause');
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Success' &&
                   $notification->getBody() === 'Gateway paused';
        });
    }

    public function test_creates_error_notification()
    {
        Notification::fake();
        
        $diagnosticInfo = ['error' => 'details'];
        
        $this->notificationService->error('Connection failed', $diagnosticInfo, true);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Error' &&
                   $notification->getBody() === 'Connection failed' &&
                   $notification->isPersistent();
        });
    }

    public function test_creates_warning_notification()
    {
        Notification::fake();
        
        $this->notificationService->warning('High failure rate detected');
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Warning' &&
                   $notification->getBody() === 'High failure rate detected' &&
                   $notification->getDuration() === 6000;
        });
    }

    public function test_creates_info_notification()
    {
        Notification::fake();
        
        $this->notificationService->info('System status updated');
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Information' &&
                   $notification->getBody() === 'System status updated' &&
                   $notification->getDuration() === 4000;
        });
    }

    public function test_connection_test_success_notification()
    {
        Notification::fake();
        
        $this->notificationService->connectionTest(true, 125.5, 42);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Success' &&
                   str_contains($notification->getBody(), 'Connection successful') &&
                   str_contains($notification->getBody(), '125.5ms') &&
                   str_contains($notification->getBody(), 'Test register value: 42');
        });
    }

    public function test_connection_test_failure_notification()
    {
        Notification::fake();
        
        $this->notificationService->connectionTest(false, 5000.0, null, 'Connection timeout');
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Error' &&
                   str_contains($notification->getBody(), 'Connection failed') &&
                   str_contains($notification->getBody(), 'Connection timeout');
        });
    }

    public function test_gateway_operation_notifications()
    {
        Notification::fake();
        
        // Test pause operation
        $this->notificationService->gatewayOperation('pause', 'Test Gateway', true);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Success' &&
                   str_contains($notification->getBody(), 'Test Gateway') &&
                   str_contains($notification->getBody(), 'paused');
        });
        
        // Test resume operation
        $this->notificationService->gatewayOperation('resume', 'Test Gateway', true);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Success' &&
                   str_contains($notification->getBody(), 'resumed');
        });
        
        // Test delete operation
        $this->notificationService->gatewayOperation('delete', 'Test Gateway', true);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Success' &&
                   str_contains($notification->getBody(), 'deleted');
        });
        
        // Test failed operation
        $this->notificationService->gatewayOperation('pause', 'Test Gateway', false);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Error' &&
                   str_contains($notification->getBody(), 'Failed to pause');
        });
    }

    public function test_bulk_operation_notifications()
    {
        Notification::fake();
        
        // Test full success
        $this->notificationService->bulkOperation('pause', 5, 5);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Success' &&
                   str_contains($notification->getBody(), 'Successfully paused 5 gateways');
        });
        
        // Test partial success
        $this->notificationService->bulkOperation('resume', 3, 5);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Warning' &&
                   str_contains($notification->getBody(), 'Partially completed') &&
                   str_contains($notification->getBody(), '3 of 5');
        });
        
        // Test complete failure
        $this->notificationService->bulkOperation('delete', 0, 5);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Error' &&
                   str_contains($notification->getBody(), 'Operation failed');
        });
    }

    public function test_data_point_operation_notifications()
    {
        Notification::fake();
        
        // Test successful operations
        $operations = ['create', 'update', 'delete', 'enable', 'disable', 'test'];
        
        foreach ($operations as $operation) {
            $this->notificationService->dataPointOperation($operation, 'Test Point', true);
            
            Notification::assertSent(function ($notification) use ($operation) {
                return $notification->getTitle() === 'Success' &&
                       str_contains($notification->getBody(), 'Test Point') &&
                       str_contains($notification->getBody(), $operation);
            });
        }
        
        // Test failed operation
        $this->notificationService->dataPointOperation('create', 'Test Point', false);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Error' &&
                   str_contains($notification->getBody(), 'Failed to create');
        });
    }

    public function test_template_operation_notifications()
    {
        Notification::fake();
        
        $this->notificationService->templateOperation('apply', 'Teltonika Template', 15);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Success' &&
                   str_contains($notification->getBody(), 'Teltonika Template') &&
                   str_contains($notification->getBody(), '15 data points');
        });
    }

    public function test_validation_error_notification()
    {
        Notification::fake();
        
        $errors = [
            'ip_address' => ['The IP address is invalid'],
            'port' => ['The port must be between 1 and 65535'],
        ];
        
        $this->notificationService->validationError($errors);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Error' &&
                   str_contains($notification->getBody(), 'IP address is invalid') &&
                   str_contains($notification->getBody(), 'port must be between');
        });
    }

    public function test_system_status_notifications()
    {
        Notification::fake();
        
        // Test different status types
        $this->notificationService->systemStatus('polling_started');
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Information' &&
                   str_contains($notification->getBody(), 'polling system has been started');
        });
        
        $this->notificationService->systemStatus('high_failure_rate');
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Warning' &&
                   str_contains($notification->getBody(), 'High failure rate detected');
        });
        
        $this->notificationService->systemStatus('system_healthy');
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Success' &&
                   str_contains($notification->getBody(), 'All systems are operating normally');
        });
        
        // Test with details
        $this->notificationService->systemStatus('polling_started', ['Gateway count: 5']);
        
        Notification::assertSent(function ($notification) {
            return str_contains($notification->getBody(), 'Gateway count: 5');
        });
    }
}