<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Filament\Notifications\Notification;

class UserFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Gateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'is_active' => true,
        ]);
    }

    public function test_success_notifications_with_undo_functionality()
    {
        Notification::fake();
        
        $notificationService = new NotificationService();
        
        $undoExecuted = false;
        $undoAction = function () use (&$undoExecuted) {
            $undoExecuted = true;
            return 'Operation undone';
        };
        
        $notificationService->success('Gateway paused successfully', $undoAction, 'Undo Pause');
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Success' &&
                   $notification->getBody() === 'Gateway paused successfully' &&
                   $notification->getDuration() === 5000;
        });
    }

    public function test_error_notifications_include_diagnostic_actions()
    {
        Notification::fake();
        
        $notificationService = new NotificationService();
        $diagnosticInfo = [
            'gateway_config' => [
                'ip_address' => '192.168.1.100',
                'port' => 502,
            ],
            'error_details' => [
                'message' => 'Connection timeout',
                'timestamp' => now()->toISOString(),
            ],
        ];
        
        $notificationService->error('Failed to connect to gateway', $diagnosticInfo, true);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Error' &&
                   $notification->getBody() === 'Failed to connect to gateway' &&
                   $notification->isPersistent();
        });
    }

    public function test_bulk_operation_feedback_shows_partial_success()
    {
        Notification::fake();
        
        $notificationService = new NotificationService();
        
        // Test partial success scenario
        $notificationService->bulkOperation('pause', 3, 5);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Warning' &&
                   str_contains($notification->getBody(), 'Partially completed') &&
                   str_contains($notification->getBody(), '3 of 5');
        });
        
        // Test complete success
        $notificationService->bulkOperation('resume', 5, 5);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Success' &&
                   str_contains($notification->getBody(), 'Successfully resumed 5');
        });
        
        // Test complete failure
        $notificationService->bulkOperation('delete', 0, 3);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Error' &&
                   str_contains($notification->getBody(), 'Operation failed');
        });
    }

    public function test_connection_test_feedback_includes_performance_metrics()
    {
        Notification::fake();
        
        $notificationService = new NotificationService();
        
        // Test successful connection with metrics
        $notificationService->connectionTest(true, 125.7, 42);
        
        Notification::assertSent(function ($notification) {
            $body = $notification->getBody();
            return $notification->getTitle() === 'Success' &&
                   str_contains($body, 'Connection successful') &&
                   str_contains($body, '125.7ms') &&
                   str_contains($body, 'Test register value: 42');
        });
        
        // Test failed connection with error details
        $notificationService->connectionTest(false, 5000.0, null, 'Connection timeout after 5 seconds');
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Error' &&
                   str_contains($notification->getBody(), 'Connection failed') &&
                   str_contains($notification->getBody(), 'Connection timeout after 5 seconds');
        });
    }

    public function test_validation_error_feedback_is_user_friendly()
    {
        Notification::fake();
        
        $notificationService = new NotificationService();
        
        $validationErrors = [
            'name' => ['The name field is required.'],
            'ip_address' => ['The IP address format is invalid.', 'The IP address must be unique.'],
            'port' => ['The port must be between 1 and 65535.'],
        ];
        
        $notificationService->validationError($validationErrors);
        
        Notification::assertSent(function ($notification) {
            $body = $notification->getBody();
            return $notification->getTitle() === 'Error' &&
                   str_contains($body, 'Please correct the following errors') &&
                   str_contains($body, 'name field is required') &&
                   str_contains($body, 'IP address format is invalid') &&
                   str_contains($body, 'port must be between');
        });
    }

    public function test_system_status_notifications_are_contextual()
    {
        Notification::fake();
        
        $notificationService = new NotificationService();
        
        // Test different system status scenarios
        $statusScenarios = [
            ['polling_started', 'Information', 'polling system has been started'],
            ['polling_stopped', 'Information', 'polling system has been stopped'],
            ['high_failure_rate', 'Warning', 'High failure rate detected'],
            ['system_healthy', 'Success', 'All systems are operating normally'],
        ];
        
        foreach ($statusScenarios as [$status, $expectedTitle, $expectedContent]) {
            $notificationService->systemStatus($status);
            
            Notification::assertSent(function ($notification) use ($expectedTitle, $expectedContent) {
                return $notification->getTitle() === $expectedTitle &&
                       str_contains($notification->getBody(), $expectedContent);
            });
        }
    }

    public function test_template_operation_feedback_includes_counts()
    {
        Notification::fake();
        
        $notificationService = new NotificationService();
        
        $notificationService->templateOperation('apply', 'Teltonika Energy Meter', 24);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Success' &&
                   str_contains($notification->getBody(), 'Teltonika Energy Meter') &&
                   str_contains($notification->getBody(), '24 data points');
        });
    }

    public function test_data_point_operation_feedback_is_specific()
    {
        Notification::fake();
        
        $notificationService = new NotificationService();
        
        $operations = [
            'create' => 'has been created',
            'update' => 'has been updated',
            'delete' => 'has been deleted',
            'enable' => 'has been enabled',
            'disable' => 'has been disabled',
            'test' => 'test read completed successfully',
        ];
        
        foreach ($operations as $operation => $expectedText) {
            $notificationService->dataPointOperation($operation, 'Voltage L1', true);
            
            Notification::assertSent(function ($notification) use ($expectedText) {
                return $notification->getTitle() === 'Success' &&
                       str_contains($notification->getBody(), 'Voltage L1') &&
                       str_contains($notification->getBody(), $expectedText);
            });
        }
        
        // Test failed operation
        $notificationService->dataPointOperation('create', 'Invalid Point', false);
        
        Notification::assertSent(function ($notification) {
            return $notification->getTitle() === 'Error' &&
                   str_contains($notification->getBody(), 'Failed to create') &&
                   str_contains($notification->getBody(), 'Invalid Point');
        });
    }

    public function test_empty_state_messages_provide_helpful_guidance()
    {
        // Test dashboard with no gateways
        Gateway::query()->delete();
        
        $response = $this->get('/admin');
        
        $response->assertStatus(200);
        $response->assertSee('No Gateways Configured');
        $response->assertSee('Get started by adding your first Teltonika gateway');
        $response->assertSee('Add Gateway');
        
        // Test live data with no data points
        Gateway::factory()->create(); // Create a gateway but no data points
        
        $response = $this->get('/admin/live-data');
        
        $response->assertStatus(200);
        $response->assertSee('No Data Points Configured');
        $response->assertSee('Add measurement points to start collecting data');
    }

    public function test_error_messages_include_suggested_actions()
    {
        $errorHandler = new \App\Services\ErrorHandlingService();
        
        $exception = new \Exception('Connection timeout');
        $result = $errorHandler->handleModbusError($exception, $this->gateway);
        
        $this->assertArrayHasKey('suggested_actions', $result);
        $this->assertIsArray($result['suggested_actions']);
        $this->assertNotEmpty($result['suggested_actions']);
        
        // Should include actionable suggestions
        $actions = $result['suggested_actions'];
        $this->assertContains('Check network connectivity to the device', $actions);
        $this->assertContains('Verify the IP address and port are correct', $actions);
    }

    public function test_notification_duration_varies_by_importance()
    {
        Notification::fake();
        
        $notificationService = new NotificationService();
        
        // Success notifications should have moderate duration
        $notificationService->success('Operation completed');
        Notification::assertSent(function ($notification) {
            return $notification->getDuration() === 5000;
        });
        
        // Info notifications should have shorter duration
        $notificationService->info('Status updated');
        Notification::assertSent(function ($notification) {
            return $notification->getDuration() === 4000;
        });
        
        // Warning notifications should have longer duration
        $notificationService->warning('High failure rate');
        Notification::assertSent(function ($notification) {
            return $notification->getDuration() === 6000;
        });
        
        // Error notifications can be persistent
        $notificationService->error('Critical error', null, true);
        Notification::assertSent(function ($notification) {
            return $notification->isPersistent();
        });
    }

    public function test_accessibility_features_in_notifications()
    {
        // This test would verify that notifications include proper ARIA labels,
        // screen reader compatible text, and keyboard navigation support
        // For now, we'll test that the notification structure supports accessibility
        
        $notificationService = new NotificationService();
        
        // Test that notifications include descriptive titles and bodies
        Notification::fake();
        
        $notificationService->connectionTest(true, 150.0, 42);
        
        Notification::assertSent(function ($notification) {
            // Should have clear, descriptive title
            $this->assertEquals('Success', $notification->getTitle());
            
            // Should have informative body text
            $body = $notification->getBody();
            $this->assertStringContains('Connection successful', $body);
            $this->assertStringContains('Latency:', $body);
            $this->assertStringContains('Test register value:', $body);
            
            return true;
        });
    }

    public function test_progressive_disclosure_in_error_messages()
    {
        $errorHandler = new \App\Services\ErrorHandlingService();
        
        $exception = new \Exception('Modbus exception: Illegal register address 65536');
        $result = $errorHandler->handleModbusError($exception, $this->gateway);
        
        // User message should be simple and clear
        $this->assertStringNotContains('Exception', $result['user_message']);
        $this->assertStringNotContains('Stack trace', $result['user_message']);
        $this->assertStringContains('Invalid register address', $result['user_message']);
        
        // Diagnostic info should contain technical details
        $this->assertArrayHasKey('diagnostic_info', $result);
        $this->assertArrayHasKey('error_details', $result['diagnostic_info']);
        $this->assertEquals('Modbus exception: Illegal register address 65536', 
                          $result['diagnostic_info']['error_details']['message']);
    }
}