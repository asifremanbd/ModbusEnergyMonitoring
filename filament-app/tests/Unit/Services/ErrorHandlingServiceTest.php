<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ErrorHandlingService;
use App\Models\Gateway;
use App\Models\DataPoint;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ErrorHandlingServiceTest extends TestCase
{
    use RefreshDatabase;

    private ErrorHandlingService $errorHandler;
    private Gateway $gateway;
    private DataPoint $dataPoint;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->errorHandler = new ErrorHandlingService();
        
        $this->gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
        ]);
        
        $this->dataPoint = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'label' => 'Test Point',
            'group_name' => 'Test Group',
        ]);
    }

    public function test_handles_connection_timeout_error()
    {
        $exception = new Exception('Connection timeout occurred');
        
        $result = $this->errorHandler->handleModbusError($exception, $this->gateway);
        
        $this->assertEquals('connection_timeout', $result['type']);
        $this->assertEquals('high', $result['severity']);
        $this->assertStringContains('timed out', $result['user_message']);
        $this->assertArrayHasKey('gateway_config', $result['diagnostic_info']);
        $this->assertIsArray($result['suggested_actions']);
        $this->assertContains('Check network connectivity to the device', $result['suggested_actions']);
    }

    public function test_handles_connection_refused_error()
    {
        $exception = new Exception('Connection refused by target machine');
        
        $result = $this->errorHandler->handleModbusError($exception, $this->gateway);
        
        $this->assertEquals('connection_refused', $result['type']);
        $this->assertEquals('high', $result['severity']);
        $this->assertStringContains('refused', $result['user_message']);
        $this->assertContains('Verify the Modbus TCP service is running on the device', $result['suggested_actions']);
    }

    public function test_handles_invalid_register_error()
    {
        $exception = new Exception('Illegal register address');
        
        $result = $this->errorHandler->handleModbusError($exception, $this->gateway, $this->dataPoint);
        
        $this->assertEquals('invalid_register', $result['type']);
        $this->assertEquals('medium', $result['severity']);
        $this->assertStringContains('Invalid register address', $result['user_message']);
        $this->assertArrayHasKey('data_point_config', $result['diagnostic_info']);
        $this->assertContains('Check the device documentation for valid register addresses', $result['suggested_actions']);
    }

    public function test_handles_data_decode_error()
    {
        $exception = new Exception('Failed to decode register data');
        
        $result = $this->errorHandler->handleModbusError($exception, $this->gateway, $this->dataPoint);
        
        $this->assertEquals('data_decode_error', $result['type']);
        $this->assertEquals('low', $result['severity']);
        $this->assertStringContains('Failed to decode data', $result['user_message']);
        $this->assertContains('Verify the data type configuration matches the device specification', $result['suggested_actions']);
    }

    public function test_handles_insufficient_registers_error()
    {
        $exception = new Exception('Insufficient registers for data type');
        
        $result = $this->errorHandler->handleModbusError($exception, $this->gateway, $this->dataPoint);
        
        $this->assertEquals('insufficient_registers', $result['type']);
        $this->assertEquals('low', $result['severity']);
        $this->assertStringContains('Insufficient register data', $result['user_message']);
        $this->assertContains('Increase the register count to match the data type requirements', $result['suggested_actions']);
    }

    public function test_handles_unknown_error()
    {
        $exception = new Exception('Some unexpected error occurred');
        
        $result = $this->errorHandler->handleModbusError($exception, $this->gateway);
        
        $this->assertEquals('unknown_error', $result['type']);
        $this->assertEquals('medium', $result['severity']);
        $this->assertStringContains('unexpected error', $result['user_message']);
        $this->assertContains('Check the device manual for troubleshooting information', $result['suggested_actions']);
    }

    public function test_includes_diagnostic_information()
    {
        $exception = new Exception('Test error');
        
        $result = $this->errorHandler->handleModbusError($exception, $this->gateway, $this->dataPoint);
        
        $diagnosticInfo = $result['diagnostic_info'];
        
        // Check gateway config
        $this->assertEquals($this->gateway->ip_address, $diagnosticInfo['gateway_config']['ip_address']);
        $this->assertEquals($this->gateway->port, $diagnosticInfo['gateway_config']['port']);
        $this->assertEquals($this->gateway->unit_id, $diagnosticInfo['gateway_config']['unit_id']);
        
        // Check error details
        $this->assertEquals('Test error', $diagnosticInfo['error_details']['message']);
        $this->assertArrayHasKey('timestamp', $diagnosticInfo['error_details']);
        
        // Check network info
        $this->assertArrayHasKey('success_count', $diagnosticInfo['network_info']);
        $this->assertArrayHasKey('failure_count', $diagnosticInfo['network_info']);
        
        // Check data point config
        $this->assertEquals($this->dataPoint->label, $diagnosticInfo['data_point_config']['label']);
        $this->assertEquals($this->dataPoint->group_name, $diagnosticInfo['data_point_config']['group']);
    }

    public function test_creates_error_notification()
    {
        $errorInfo = [
            'type' => 'connection_timeout',
            'user_message' => 'Connection timed out',
            'severity' => 'high',
            'diagnostic_info' => ['test' => 'data'],
        ];
        
        $notification = $this->errorHandler->createErrorNotification($errorInfo);
        
        $this->assertEquals('Communication Error', $notification->getTitle());
        $this->assertEquals('Connection timed out', $notification->getBody());
        $this->assertTrue($notification->isPersistent());
    }

    public function test_creates_success_notification()
    {
        $undoAction = function () {
            return 'undone';
        };
        
        $notification = $this->errorHandler->createSuccessNotification('Operation completed', $undoAction);
        
        $this->assertEquals('Success', $notification->getTitle());
        $this->assertEquals('Operation completed', $notification->getBody());
        $this->assertEquals(5000, $notification->getDuration());
    }

    public function test_gets_empty_state_messages()
    {
        $noGateways = $this->errorHandler->getEmptyStateMessage('no_gateways');
        $this->assertEquals('No Gateways Configured', $noGateways['title']);
        $this->assertStringContains('adding your first Teltonika gateway', $noGateways['message']);
        $this->assertEquals('Add Gateway', $noGateways['action_label']);
        
        $noDataPoints = $this->errorHandler->getEmptyStateMessage('no_data_points');
        $this->assertEquals('No Data Points Configured', $noDataPoints['title']);
        $this->assertStringContains('Add measurement points', $noDataPoints['message']);
        
        $noReadings = $this->errorHandler->getEmptyStateMessage('no_readings');
        $this->assertEquals('No Recent Data', $noReadings['title']);
        $this->assertStringContains('No readings have been collected', $noReadings['message']);
        
        $gatewayOffline = $this->errorHandler->getEmptyStateMessage('gateway_offline', [
            'gateway_name' => 'Test Gateway'
        ]);
        $this->assertEquals('Gateway Offline', $gatewayOffline['title']);
        $this->assertStringContains('Test Gateway', $gatewayOffline['message']);
        
        $highFailureRate = $this->errorHandler->getEmptyStateMessage('high_failure_rate', [
            'gateway_name' => 'Test Gateway',
            'failure_rate' => '85%'
        ]);
        $this->assertEquals('High Failure Rate Detected', $highFailureRate['title']);
        $this->assertStringContains('85%', $highFailureRate['message']);
    }

    public function test_categorizes_errors_correctly()
    {
        $testCases = [
            ['Connection timeout', 'connection_timeout'],
            ['Connection refused', 'connection_refused'],
            ['Illegal register', 'invalid_register'],
            ['Unsupported function code', 'unsupported_function'],
            ['Failed to decode', 'data_decode_error'],
            ['Insufficient registers', 'insufficient_registers'],
            ['Random error message', 'unknown_error'],
        ];
        
        foreach ($testCases as [$message, $expectedType]) {
            $exception = new Exception($message);
            $result = $this->errorHandler->handleModbusError($exception, $this->gateway);
            $this->assertEquals($expectedType, $result['type'], "Failed for message: {$message}");
        }
    }

    public function test_generates_user_friendly_messages()
    {
        $exception = new Exception('Connection timeout');
        $result = $this->errorHandler->handleModbusError($exception, $this->gateway, $this->dataPoint);
        
        $message = $result['user_message'];
        
        // Should include gateway info
        $this->assertStringContains($this->gateway->name, $message);
        $this->assertStringContains($this->gateway->ip_address, $message);
        $this->assertStringContains((string)$this->gateway->port, $message);
        
        // Should include data point info when provided
        $this->assertStringContains($this->dataPoint->label, $message);
        
        // Should be user-friendly
        $this->assertStringNotContains('Exception', $message);
        $this->assertStringNotContains('Stack trace', $message);
    }

    public function test_provides_appropriate_suggested_actions()
    {
        $testCases = [
            'connection_timeout' => 'Check network connectivity',
            'connection_refused' => 'Verify the Modbus TCP service',
            'invalid_register' => 'Check the device documentation',
            'unsupported_function' => 'Check if the device supports',
            'data_decode_error' => 'Verify the data type configuration',
            'insufficient_registers' => 'Increase the register count',
        ];
        
        foreach ($testCases as $errorType => $expectedAction) {
            $exception = new Exception($errorType);
            $result = $this->errorHandler->handleModbusError($exception, $this->gateway);
            
            $actions = $result['suggested_actions'];
            $this->assertIsArray($actions);
            $this->assertNotEmpty($actions);
            
            $foundExpectedAction = false;
            foreach ($actions as $action) {
                if (str_contains($action, $expectedAction)) {
                    $foundExpectedAction = true;
                    break;
                }
            }
            
            $this->assertTrue($foundExpectedAction, "Expected action '{$expectedAction}' not found for error type '{$errorType}'");
        }
    }
}