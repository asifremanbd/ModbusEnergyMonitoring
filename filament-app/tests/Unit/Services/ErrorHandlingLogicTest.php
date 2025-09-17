<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ErrorHandlingService;
use App\Models\Gateway;
use App\Models\DataPoint;
use Exception;

class ErrorHandlingLogicTest extends TestCase
{
    private ErrorHandlingService $errorHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorHandler = new ErrorHandlingService();
    }

    public function test_categorizes_connection_timeout_error()
    {
        $exception = new Exception('Connection timeout occurred');
        $gateway = $this->createMockGateway();
        
        $result = $this->errorHandler->handleModbusError($exception, $gateway);
        
        $this->assertEquals('connection_timeout', $result['type']);
        $this->assertEquals('high', $result['severity']);
        $this->assertStringContainsString('timed out', $result['user_message']);
    }

    public function test_categorizes_connection_refused_error()
    {
        $exception = new Exception('Connection refused by target machine');
        $gateway = $this->createMockGateway();
        
        $result = $this->errorHandler->handleModbusError($exception, $gateway);
        
        $this->assertEquals('connection_refused', $result['type']);
        $this->assertEquals('high', $result['severity']);
        $this->assertStringContainsString('refused', $result['user_message']);
    }

    public function test_categorizes_invalid_register_error()
    {
        $exception = new Exception('Illegal register address');
        $gateway = $this->createMockGateway();
        $dataPoint = $this->createMockDataPoint();
        
        $result = $this->errorHandler->handleModbusError($exception, $gateway, $dataPoint);
        
        $this->assertEquals('invalid_register', $result['type']);
        $this->assertEquals('medium', $result['severity']);
        $this->assertStringContainsString('Invalid register address', $result['user_message']);
    }

    public function test_categorizes_data_decode_error()
    {
        $exception = new Exception('Failed to decode register data');
        $gateway = $this->createMockGateway();
        
        $result = $this->errorHandler->handleModbusError($exception, $gateway);
        
        $this->assertEquals('data_decode_error', $result['type']);
        $this->assertEquals('low', $result['severity']);
        $this->assertStringContainsString('Failed to decode data', $result['user_message']);
    }

    public function test_categorizes_unknown_error()
    {
        $exception = new Exception('Some unexpected error occurred');
        $gateway = $this->createMockGateway();
        
        $result = $this->errorHandler->handleModbusError($exception, $gateway);
        
        $this->assertEquals('unknown_error', $result['type']);
        $this->assertEquals('medium', $result['severity']);
        $this->assertStringContainsString('unexpected error', $result['user_message']);
    }

    public function test_provides_diagnostic_information()
    {
        $exception = new Exception('Test error');
        $gateway = $this->createMockGateway();
        $dataPoint = $this->createMockDataPoint();
        
        $result = $this->errorHandler->handleModbusError($exception, $gateway, $dataPoint);
        
        $this->assertArrayHasKey('diagnostic_info', $result);
        $diagnosticInfo = $result['diagnostic_info'];
        
        // Check gateway config
        $this->assertArrayHasKey('gateway_config', $diagnosticInfo);
        $this->assertEquals('192.168.1.100', $diagnosticInfo['gateway_config']['ip_address']);
        $this->assertEquals(502, $diagnosticInfo['gateway_config']['port']);
        
        // Check error details
        $this->assertArrayHasKey('error_details', $diagnosticInfo);
        $this->assertEquals('Test error', $diagnosticInfo['error_details']['message']);
        
        // Check data point config when provided
        $this->assertArrayHasKey('data_point_config', $diagnosticInfo);
        $this->assertEquals('Test Point', $diagnosticInfo['data_point_config']['label']);
    }

    public function test_provides_suggested_actions()
    {
        $testCases = [
            'Connection timeout occurred' => 'Check network connectivity',
            'Connection refused by target machine' => 'Verify the Modbus TCP service',
            'Illegal register address' => 'Check the device documentation',
            'Failed to decode register data' => 'Verify the data type configuration',
        ];
        
        foreach ($testCases as $errorMessage => $expectedAction) {
            $exception = new Exception($errorMessage);
            $gateway = $this->createMockGateway();
            
            $result = $this->errorHandler->handleModbusError($exception, $gateway);
            
            $this->assertArrayHasKey('suggested_actions', $result);
            $this->assertIsArray($result['suggested_actions']);
            $this->assertNotEmpty($result['suggested_actions']);
            
            $foundExpectedAction = false;
            foreach ($result['suggested_actions'] as $action) {
                if (str_contains($action, $expectedAction)) {
                    $foundExpectedAction = true;
                    break;
                }
            }
            
            $this->assertTrue($foundExpectedAction, "Expected action '{$expectedAction}' not found for error message '{$errorMessage}'");
        }
    }

    public function test_generates_user_friendly_messages()
    {
        $exception = new Exception('Connection timeout');
        $gateway = $this->createMockGateway();
        $dataPoint = $this->createMockDataPoint();
        
        $result = $this->errorHandler->handleModbusError($exception, $gateway, $dataPoint);
        
        $message = $result['user_message'];
        
        // Should include gateway info
        $this->assertStringContainsString('Test Gateway', $message);
        $this->assertStringContainsString('192.168.1.100', $message);
        $this->assertStringContainsString('502', $message);
        
        // Should include data point info when provided
        $this->assertStringContainsString('Test Point', $message);
        
        // Should be user-friendly
        $this->assertStringNotContainsString('Exception', $message);
        $this->assertStringNotContainsString('Stack trace', $message);
    }

    public function test_gets_empty_state_messages()
    {
        $noGateways = $this->errorHandler->getEmptyStateMessage('no_gateways');
        $this->assertEquals('No Gateways Configured', $noGateways['title']);
        $this->assertStringContainsString('adding your first Teltonika gateway', $noGateways['message']);
        $this->assertEquals('Add Gateway', $noGateways['action_label']);
        
        $noDataPoints = $this->errorHandler->getEmptyStateMessage('no_data_points');
        $this->assertEquals('No Data Points Configured', $noDataPoints['title']);
        $this->assertStringContainsString('Add measurement points', $noDataPoints['message']);
        
        $gatewayOffline = $this->errorHandler->getEmptyStateMessage('gateway_offline', [
            'gateway_name' => 'Test Gateway'
        ]);
        $this->assertEquals('Gateway Offline', $gatewayOffline['title']);
        $this->assertStringContainsString('Test Gateway', $gatewayOffline['message']);
    }

    public function test_error_severity_mapping()
    {
        $severityTests = [
            'Connection timeout occurred' => 'high',
            'Connection refused by target machine' => 'high',
            'Illegal register address' => 'medium',
            'Unsupported function code' => 'medium',
            'Failed to decode register data' => 'low',
            'Insufficient registers for data type' => 'low',
            'Some random error' => 'medium',
        ];
        
        foreach ($severityTests as $errorMessage => $expectedSeverity) {
            $exception = new Exception($errorMessage);
            $gateway = $this->createMockGateway();
            
            $result = $this->errorHandler->handleModbusError($exception, $gateway);
            
            $this->assertEquals($expectedSeverity, $result['severity'], 
                "Expected severity '{$expectedSeverity}' for error message '{$errorMessage}'");
        }
    }

    private function createMockGateway(): Gateway
    {
        $gateway = new Gateway();
        $gateway->id = 1;
        $gateway->name = 'Test Gateway';
        $gateway->ip_address = '192.168.1.100';
        $gateway->port = 502;
        $gateway->unit_id = 1;
        $gateway->poll_interval = 10;
        $gateway->is_active = true;
        $gateway->success_count = 100;
        $gateway->failure_count = 5;
        
        return $gateway;
    }

    private function createMockDataPoint(): DataPoint
    {
        $dataPoint = new DataPoint();
        $dataPoint->id = 1;
        $dataPoint->gateway_id = 1;
        $dataPoint->label = 'Test Point';
        $dataPoint->group_name = 'Test Group';
        $dataPoint->modbus_function = 4;
        $dataPoint->register_address = 1000;
        $dataPoint->register_count = 2;
        $dataPoint->data_type = 'float32';
        $dataPoint->byte_order = 'word_swapped';
        $dataPoint->scale_factor = 1.0;
        $dataPoint->is_enabled = true;
        
        return $dataPoint;
    }
}