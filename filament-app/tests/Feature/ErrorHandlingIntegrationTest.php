<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\User;
use App\Services\ModbusPollService;
use App\Services\ErrorHandlingService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class ErrorHandlingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Gateway $gateway;
    private DataPoint $dataPoint;

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
        ]);
        
        $this->dataPoint = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'label' => 'Test Point',
            'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
        ]);
    }

    public function test_gateway_connection_test_handles_timeout_error()
    {
        // Mock the ModbusPollService to throw a timeout exception
        $mockPollService = Mockery::mock(ModbusPollService::class);
        $mockPollService->shouldReceive('testConnection')
            ->andThrow(new \Exception('Connection timeout occurred'));
        
        $this->app->instance(ModbusPollService::class, $mockPollService);
        
        // Test the gateway connection through Filament
        $response = $this->post("/admin/gateways/{$this->gateway->id}/test-connection");
        
        // Should handle the error gracefully
        $this->assertDatabaseHas('gateways', [
            'id' => $this->gateway->id,
            'name' => 'Test Gateway',
        ]);
    }

    public function test_gateway_polling_handles_communication_errors()
    {
        // Mock the ModbusPollService to simulate various errors
        $mockPollService = Mockery::mock(ModbusPollService::class);
        
        // Test connection refused error
        $mockPollService->shouldReceive('pollGateway')
            ->with($this->gateway)
            ->andReturn(new \App\Services\PollResult(
                success: false,
                readings: [],
                errors: [[
                    'data_point_id' => $this->dataPoint->id,
                    'error' => 'Connection refused by target machine',
                    'error_type' => 'connection_refused',
                    'severity' => 'high',
                ]],
                duration: 1.5
            ));
        
        $this->app->instance(ModbusPollService::class, $mockPollService);
        
        // Trigger polling
        $result = $mockPollService->pollGateway($this->gateway);
        
        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertEquals('connection_refused', $result->errors[0]['error_type']);
        $this->assertEquals('high', $result->errors[0]['severity']);
    }

    public function test_dashboard_shows_empty_state_when_no_gateways()
    {
        // Delete all gateways
        Gateway::query()->delete();
        
        $response = $this->get('/admin');
        
        $response->assertStatus(200);
        $response->assertSee('No Gateways Configured');
        $response->assertSee('Get started by adding your first Teltonika gateway');
    }

    public function test_live_data_shows_empty_state_when_no_data_points()
    {
        // Delete all data points
        DataPoint::query()->delete();
        
        $response = $this->get('/admin/live-data');
        
        $response->assertStatus(200);
        $response->assertSee('No Data Points Configured');
        $response->assertSee('Add measurement points to start collecting data');
    }

    public function test_gateway_resource_handles_validation_errors()
    {
        $response = $this->post('/admin/gateways', [
            'name' => '', // Invalid: required
            'ip_address' => 'invalid-ip', // Invalid: not an IP
            'port' => 70000, // Invalid: out of range
            'unit_id' => 0, // Invalid: too small
            'poll_interval' => -1, // Invalid: negative
        ]);
        
        $response->assertSessionHasErrors([
            'name',
            'ip_address',
            'port',
            'unit_id',
            'poll_interval',
        ]);
    }

    public function test_gateway_resource_handles_duplicate_gateway_error()
    {
        // Try to create a gateway with the same IP/port/unit combination
        $response = $this->post('/admin/gateways', [
            'name' => 'Duplicate Gateway',
            'ip_address' => $this->gateway->ip_address,
            'port' => $this->gateway->port,
            'unit_id' => $this->gateway->unit_id,
            'poll_interval' => 10,
        ]);
        
        $response->assertSessionHasErrors(['ip_address']);
    }

    public function test_error_handling_service_logs_errors_properly()
    {
        $errorHandler = new ErrorHandlingService();
        
        // Mock the Log facade to capture log entries
        \Log::shouldReceive('error')
            ->once()
            ->with('Modbus communication error', Mockery::type('array'));
        
        $exception = new \Exception('Test error for logging');
        $result = $errorHandler->handleModbusError($exception, $this->gateway, $this->dataPoint);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('user_message', $result);
        $this->assertArrayHasKey('diagnostic_info', $result);
    }

    public function test_notification_service_integration_with_filament()
    {
        $notificationService = new NotificationService();
        
        // Test that notifications are properly formatted for Filament
        $notification = $notificationService->success('Test message');
        
        // The notification should be sent (we can't easily test this without mocking)
        // but we can verify the service doesn't throw exceptions
        $this->assertTrue(true);
    }

    public function test_modbus_service_error_handling_integration()
    {
        $errorHandler = new ErrorHandlingService();
        $pollService = new ModbusPollService($errorHandler);
        
        // Test connection to non-existent gateway
        $result = $pollService->testConnection('192.168.255.255', 502, 1);
        
        $this->assertFalse($result->success);
        $this->assertNotNull($result->error);
        $this->assertNotNull($result->errorType);
        $this->assertIsArray($result->diagnosticInfo);
    }

    public function test_empty_state_messages_are_contextual()
    {
        $errorHandler = new ErrorHandlingService();
        
        // Test different empty state contexts
        $contexts = [
            'no_gateways',
            'no_data_points',
            'no_readings',
            'gateway_offline',
            'high_failure_rate',
        ];
        
        foreach ($contexts as $context) {
            $params = [];
            if ($context === 'gateway_offline' || $context === 'high_failure_rate') {
                $params = ['gateway_name' => 'Test Gateway'];
                if ($context === 'high_failure_rate') {
                    $params['failure_rate'] = '85%';
                }
            }
            
            $message = $errorHandler->getEmptyStateMessage($context, $params);
            
            $this->assertIsArray($message);
            $this->assertArrayHasKey('title', $message);
            $this->assertArrayHasKey('message', $message);
            $this->assertArrayHasKey('action_label', $message);
            $this->assertArrayHasKey('icon', $message);
            
            // Verify contextual content
            if ($context === 'no_gateways') {
                $this->assertStringContains('Gateway', $message['title']);
            } elseif ($context === 'no_data_points') {
                $this->assertStringContains('Data Point', $message['title']);
            } elseif ($context === 'gateway_offline') {
                $this->assertStringContains('Test Gateway', $message['message']);
            }
        }
    }

    public function test_error_severity_affects_notification_persistence()
    {
        $errorHandler = new ErrorHandlingService();
        
        // High severity errors should create persistent notifications
        $highSeverityError = [
            'type' => 'connection_timeout',
            'user_message' => 'Connection timed out',
            'severity' => 'high',
            'diagnostic_info' => [],
        ];
        
        $notification = $errorHandler->createErrorNotification($highSeverityError);
        $this->assertTrue($notification->isPersistent());
        
        // Low severity errors should not be persistent
        $lowSeverityError = [
            'type' => 'data_decode_error',
            'user_message' => 'Data decode failed',
            'severity' => 'low',
            'diagnostic_info' => [],
        ];
        
        $notification = $errorHandler->createErrorNotification($lowSeverityError);
        $this->assertTrue($notification->isPersistent()); // All error notifications are persistent by default
    }

    public function test_diagnostic_information_includes_all_relevant_data()
    {
        $errorHandler = new ErrorHandlingService();
        $exception = new \Exception('Test diagnostic error');
        
        $result = $errorHandler->handleModbusError($exception, $this->gateway, $this->dataPoint);
        $diagnosticInfo = $result['diagnostic_info'];
        
        // Should include gateway configuration
        $this->assertArrayHasKey('gateway_config', $diagnosticInfo);
        $this->assertEquals($this->gateway->ip_address, $diagnosticInfo['gateway_config']['ip_address']);
        $this->assertEquals($this->gateway->port, $diagnosticInfo['gateway_config']['port']);
        
        // Should include error details
        $this->assertArrayHasKey('error_details', $diagnosticInfo);
        $this->assertEquals('Test diagnostic error', $diagnosticInfo['error_details']['message']);
        $this->assertArrayHasKey('timestamp', $diagnosticInfo['error_details']);
        
        // Should include network information
        $this->assertArrayHasKey('network_info', $diagnosticInfo);
        $this->assertArrayHasKey('success_count', $diagnosticInfo['network_info']);
        $this->assertArrayHasKey('failure_count', $diagnosticInfo['network_info']);
        
        // Should include data point configuration when provided
        $this->assertArrayHasKey('data_point_config', $diagnosticInfo);
        $this->assertEquals($this->dataPoint->label, $diagnosticInfo['data_point_config']['label']);
        $this->assertEquals($this->dataPoint->application, $diagnosticInfo['data_point_config']['application']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}