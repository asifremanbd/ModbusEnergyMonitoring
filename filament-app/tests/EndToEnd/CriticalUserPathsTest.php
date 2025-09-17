<?php

namespace Tests\EndToEnd;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Models\User;
use App\Services\ModbusPollService;
use App\Services\GatewayManagementService;
use App\Services\TeltonikaTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalUserPathsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function critical_path_new_user_complete_setup_workflow()
    {
        // Step 1: New user logs in and sees empty dashboard
        $dashboardResponse = $this->get('/admin');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('0'); // No gateways
        $dashboardResponse->assertSee('No gateways configured'); // Empty state message

        // Step 2: User navigates to create first gateway
        $createGatewayResponse = $this->get('/admin/gateways/create');
        $createGatewayResponse->assertStatus(200);
        $createGatewayResponse->assertSee('Connect'); // Wizard step 1

        // Step 3: User fills in connection details
        $connectionData = [
            'name' => 'Main Production Meter',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'unit_id' => 1,
            'poll_interval' => 10,
        ];

        // Mock connection test
        $this->mock(ModbusPollService::class, function ($mock) {
            $mock->shouldReceive('testConnection')
                ->once()
                ->with('192.168.1.100', 502, 1)
                ->andReturn(new \App\Services\ConnectionTest(
                    success: true,
                    latency: 25.5,
                    testValue: 12345,
                    error: null
                ));
        });

        // Test connection
        $testResponse = $this->post('/admin/gateways/test-connection', $connectionData);
        $testResponse->assertStatus(200);
        $testResponse->assertJson(['success' => true]);

        // Step 4: User proceeds to data point mapping
        $createResponse = $this->post('/admin/gateways', array_merge($connectionData, [
            'is_active' => false, // Don't start polling yet
        ]));
        $createResponse->assertRedirect();

        $gateway = Gateway::where('name', 'Main Production Meter')->first();
        $this->assertNotNull($gateway);

        // Step 5: User applies Teltonika template
        $templateService = app(TeltonikaTemplateService::class);
        $template = $templateService->getDefaultTemplate();

        foreach ($template['data_points'] as $pointData) {
            DataPoint::create([
                'gateway_id' => $gateway->id,
                'group_name' => $pointData['group_name'],
                'label' => $pointData['label'],
                'modbus_function' => $pointData['modbus_function'],
                'register_address' => $pointData['register_address'],
                'register_count' => $pointData['register_count'],
                'data_type' => $pointData['data_type'],
                'byte_order' => $pointData['byte_order'],
                'scale_factor' => $pointData['scale_factor'],
                'is_enabled' => true,
            ]);
        }

        // Verify data points were created
        $this->assertGreaterThan(0, $gateway->dataPoints()->count());

        // Step 6: User tests a data point reading
        $dataPoint = $gateway->dataPoints()->first();
        
        $this->mock(ModbusPollService::class, function ($mock) use ($gateway, $dataPoint) {
            $mock->shouldReceive('readRegister')
                ->once()
                ->with($gateway, $dataPoint)
                ->andReturn(new \App\Services\ReadingResult(
                    success: true,
                    rawValue: '[12345, 67890]',
                    scaledValue: 230.5,
                    quality: 'good',
                    error: null
                ));
        });

        $testReadResponse = $this->post("/admin/data-points/{$dataPoint->id}/test-read");
        $testReadResponse->assertStatus(200);

        // Step 7: User starts polling
        $this->mock(GatewayManagementService::class, function ($mock) use ($gateway) {
            $mock->shouldReceive('resumePolling')
                ->once()
                ->with($gateway)
                ->andReturnNull();
        });

        $startPollingResponse = $this->post("/admin/gateways/{$gateway->id}/resume");
        $startPollingResponse->assertRedirect();

        $gateway->refresh();
        $this->assertTrue($gateway->is_active);

        // Step 8: Simulate some readings being collected
        foreach ($gateway->dataPoints->take(5) as $dataPoint) {
            Reading::factory()->create([
                'data_point_id' => $dataPoint->id,
                'scaled_value' => rand(200, 250),
                'quality' => 'good',
                'read_at' => now()->subMinutes(rand(1, 10)),
            ]);
        }

        // Step 9: User views updated dashboard with data
        $updatedDashboardResponse = $this->get('/admin');
        $updatedDashboardResponse->assertStatus(200);
        $updatedDashboardResponse->assertSee('1'); // One gateway online
        $updatedDashboardResponse->assertSee('Main Production Meter');
        $updatedDashboardResponse->assertDontSee('No gateways configured');

        // Step 10: User views live data
        $liveDataResponse = $this->get('/admin/live-data');
        $liveDataResponse->assertStatus(200);
        $liveDataResponse->assertSee('Main Production Meter');
        $liveDataResponse->assertSee('good'); // Quality indicator

        // Verify complete setup success
        $this->assertEquals(1, Gateway::count());
        $this->assertGreaterThan(0, DataPoint::count());
        $this->assertGreaterThan(0, Reading::count());
    }

    /** @test */
    public function critical_path_troubleshooting_offline_gateway()
    {
        // Setup: Create gateway that will go offline
        $gateway = Gateway::factory()->create([
            'name' => 'Problematic Gateway',
            'ip_address' => '192.168.1.200',
            'is_active' => true,
            'last_seen_at' => now()->subMinutes(30), // Offline
            'success_count' => 100,
            'failure_count' => 25,
        ]);

        DataPoint::factory()->count(10)->create([
            'gateway_id' => $gateway->id,
        ]);

        // Step 1: User notices gateway is offline on dashboard
        $dashboardResponse = $this->get('/admin');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Problematic Gateway');
        $dashboardResponse->assertSee('offline'); // Status indicator

        // Step 2: User clicks on gateway to investigate
        $gatewayDetailResponse = $this->get("/admin/gateways/{$gateway->id}");
        $gatewayDetailResponse->assertStatus(200);
        $gatewayDetailResponse->assertSee('25'); // Failure count
        $gatewayDetailResponse->assertSee('80'); // Success rate (100/(100+25)*100)

        // Step 3: User tests connection to diagnose issue
        $this->mock(ModbusPollService::class, function ($mock) {
            $mock->shouldReceive('testConnection')
                ->once()
                ->with('192.168.1.200', 502, 1)
                ->andReturn(new \App\Services\ConnectionTest(
                    success: false,
                    latency: 5000.0,
                    testValue: null,
                    error: 'Connection timeout'
                ));
        });

        $testConnectionResponse = $this->post("/admin/gateways/{$gateway->id}/test-connection");
        $testConnectionResponse->assertRedirect();
        $testConnectionResponse->assertSessionHas('filament.notifications');

        // Step 4: User updates IP address to fix connection
        $updateResponse = $this->put("/admin/gateways/{$gateway->id}", [
            'name' => $gateway->name,
            'ip_address' => '192.168.1.201', // Corrected IP
            'port' => $gateway->port,
            'unit_id' => $gateway->unit_id,
            'poll_interval' => $gateway->poll_interval,
            'is_active' => true,
        ]);

        $gateway->refresh();
        $this->assertEquals('192.168.1.201', $gateway->ip_address);

        // Step 5: User retests connection
        $this->mock(ModbusPollService::class, function ($mock) {
            $mock->shouldReceive('testConnection')
                ->once()
                ->with('192.168.1.201', 502, 1)
                ->andReturn(new \App\Services\ConnectionTest(
                    success: true,
                    latency: 28.3,
                    testValue: 54321,
                    error: null
                ));
        });

        $retestResponse = $this->post("/admin/gateways/{$gateway->id}/test-connection");
        $retestResponse->assertRedirect();

        // Step 6: User restarts polling
        $this->mock(GatewayManagementService::class, function ($mock) use ($gateway) {
            $mock->shouldReceive('resumePolling')
                ->once()
                ->with($gateway)
                ->andReturnNull();
        });

        $resumeResponse = $this->post("/admin/gateways/{$gateway->id}/resume");
        $resumeResponse->assertRedirect();

        // Step 7: Simulate successful readings after fix
        $dataPoints = $gateway->dataPoints->take(3);
        foreach ($dataPoints as $dataPoint) {
            Reading::factory()->create([
                'data_point_id' => $dataPoint->id,
                'quality' => 'good',
                'read_at' => now(),
            ]);
        }

        // Update gateway status
        $gateway->update([
            'last_seen_at' => now(),
            'success_count' => $gateway->success_count + 3,
        ]);

        // Step 8: User verifies gateway is back online
        $verifyDashboardResponse = $this->get('/admin');
        $verifyDashboardResponse->assertStatus(200);
        $verifyDashboardResponse->assertSee('online'); // Should show online now

        $verifyLiveDataResponse = $this->get('/admin/live-data');
        $verifyLiveDataResponse->assertStatus(200);
        $verifyLiveDataResponse->assertSee('good'); // Quality should be good
    }

    /** @test */
    public function critical_path_scaling_to_multiple_gateways()
    {
        // Step 1: User starts with successful single gateway
        $firstGateway = Gateway::factory()->create([
            'name' => 'Gateway 1',
            'is_active' => true,
        ]);

        DataPoint::factory()->count(15)->create([
            'gateway_id' => $firstGateway->id,
        ]);

        // Create some readings
        foreach ($firstGateway->dataPoints->take(5) as $dataPoint) {
            Reading::factory()->count(10)->create([
                'data_point_id' => $dataPoint->id,
                'read_at' => now()->subMinutes(rand(1, 60)),
            ]);
        }

        // Step 2: User adds multiple gateways rapidly
        $gatewayData = [];
        for ($i = 2; $i <= 10; $i++) {
            $gatewayData[] = [
                'name' => "Gateway {$i}",
                'ip_address' => "192.168.1." . (100 + $i),
                'port' => 502,
                'unit_id' => 1,
                'poll_interval' => 10,
                'is_active' => true,
            ];
        }

        foreach ($gatewayData as $data) {
            $response = $this->post('/admin/gateways', $data);
            $response->assertRedirect();
        }

        // Verify all gateways created
        $this->assertEquals(10, Gateway::count());

        // Step 3: User applies template to all new gateways
        $newGateways = Gateway::where('id', '>', $firstGateway->id)->get();
        
        foreach ($newGateways as $gateway) {
            DataPoint::factory()->count(12)->create([
                'gateway_id' => $gateway->id,
                'is_enabled' => true,
            ]);
        }

        // Step 4: User views dashboard with multiple gateways
        $dashboardResponse = $this->get('/admin');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('10'); // Total gateways

        // Step 5: User performs bulk operations
        $gatewayIds = Gateway::pluck('id')->toArray();

        // Mock bulk pause
        $this->mock(GatewayManagementService::class, function ($mock) {
            $mock->shouldReceive('pausePolling')
                ->times(10)
                ->andReturnNull();
        });

        $bulkPauseResponse = $this->post('/admin/gateways/bulk-pause', [
            'selected' => $gatewayIds,
        ]);

        // Verify all gateways paused
        Gateway::chunk(100, function ($gateways) {
            foreach ($gateways as $gateway) {
                $gateway->update(['is_active' => false]);
            }
        });

        $this->assertEquals(0, Gateway::where('is_active', true)->count());

        // Step 6: User resumes all gateways
        $this->mock(GatewayManagementService::class, function ($mock) {
            $mock->shouldReceive('resumePolling')
                ->times(10)
                ->andReturnNull();
        });

        $bulkResumeResponse = $this->post('/admin/gateways/bulk-resume', [
            'selected' => $gatewayIds,
        ]);

        // Update all gateways to active
        Gateway::chunk(100, function ($gateways) {
            foreach ($gateways as $gateway) {
                $gateway->update(['is_active' => true]);
            }
        });

        // Step 7: User views live data with all gateways
        // Create readings for all gateways
        foreach (Gateway::all() as $gateway) {
            foreach ($gateway->dataPoints->take(3) as $dataPoint) {
                Reading::factory()->create([
                    'data_point_id' => $dataPoint->id,
                    'read_at' => now()->subMinutes(rand(1, 10)),
                ]);
            }
        }

        $liveDataResponse = $this->get('/admin/live-data');
        $liveDataResponse->assertStatus(200);

        // Should see data from multiple gateways
        $liveDataResponse->assertSee('Gateway 1');
        $liveDataResponse->assertSee('Gateway 5');
        $liveDataResponse->assertSee('Gateway 10');

        // Step 8: User filters data by specific gateway
        $filterResponse = $this->get('/admin/live-data?gateway=' . $firstGateway->id);
        $filterResponse->assertStatus(200);
        $filterResponse->assertSee('Gateway 1');
    }

    /** @test */
    public function critical_path_data_point_configuration_and_validation()
    {
        // Setup: Create gateway
        $gateway = Gateway::factory()->create([
            'name' => 'Configuration Test Gateway',
        ]);

        // Step 1: User creates custom data point configuration
        $customDataPoint = DataPoint::create([
            'gateway_id' => $gateway->id,
            'group_name' => 'Custom_Measurements',
            'label' => 'Power Factor',
            'modbus_function' => 4,
            'register_address' => 500,
            'register_count' => 2,
            'data_type' => 'float32',
            'byte_order' => 'word_swapped',
            'scale_factor' => 0.001,
            'is_enabled' => true,
        ]);

        // Step 2: User tests the data point
        $this->mock(ModbusPollService::class, function ($mock) use ($gateway, $customDataPoint) {
            $mock->shouldReceive('readRegister')
                ->once()
                ->with($gateway, $customDataPoint)
                ->andReturn(new \App\Services\ReadingResult(
                    success: true,
                    rawValue: '[16384, 0]',
                    scaledValue: 0.95,
                    quality: 'good',
                    error: null
                ));
        });

        $testResponse = $this->post("/admin/data-points/{$customDataPoint->id}/test-read");
        $testResponse->assertStatus(200);

        // Step 3: User clones configuration to multiple groups
        $groups = ['Meter_1', 'Meter_2', 'Meter_3'];
        $registerOffset = 100;

        foreach ($groups as $index => $group) {
            DataPoint::create([
                'gateway_id' => $gateway->id,
                'group_name' => $group,
                'label' => 'Power Factor',
                'modbus_function' => 4,
                'register_address' => 500 + ($index * $registerOffset),
                'register_count' => 2,
                'data_type' => 'float32',
                'byte_order' => 'word_swapped',
                'scale_factor' => 0.001,
                'is_enabled' => true,
            ]);
        }

        // Verify cloned data points
        $this->assertEquals(4, $gateway->dataPoints()->count()); // Original + 3 clones

        // Step 4: User validates different data types
        $dataTypes = [
            ['type' => 'int16', 'count' => 1, 'test_value' => 32000],
            ['type' => 'uint32', 'count' => 2, 'test_value' => 4000000000],
            ['type' => 'float64', 'count' => 4, 'test_value' => 123.456789],
        ];

        foreach ($dataTypes as $typeConfig) {
            $testDataPoint = DataPoint::create([
                'gateway_id' => $gateway->id,
                'group_name' => 'Type_Tests',
                'label' => "Test {$typeConfig['type']}",
                'modbus_function' => 4,
                'register_address' => 1000 + rand(1, 100),
                'register_count' => $typeConfig['count'],
                'data_type' => $typeConfig['type'],
                'byte_order' => 'big_endian',
                'scale_factor' => 1.0,
                'is_enabled' => true,
            ]);

            // Mock test reading for each data type
            $this->mock(ModbusPollService::class, function ($mock) use ($gateway, $testDataPoint, $typeConfig) {
                $mock->shouldReceive('readRegister')
                    ->once()
                    ->with($gateway, $testDataPoint)
                    ->andReturn(new \App\Services\ReadingResult(
                        success: true,
                        rawValue: '[12345, 67890]',
                        scaledValue: $typeConfig['test_value'],
                        quality: 'good',
                        error: null
                    ));
            });

            $typeTestResponse = $this->post("/admin/data-points/{$testDataPoint->id}/test-read");
            $typeTestResponse->assertStatus(200);
        }

        // Step 5: User enables/disables data points selectively
        $allDataPoints = $gateway->dataPoints;
        
        // Disable half of the data points
        $toDisable = $allDataPoints->take(ceil($allDataPoints->count() / 2));
        foreach ($toDisable as $dataPoint) {
            $dataPoint->update(['is_enabled' => false]);
        }

        $enabledCount = $gateway->dataPoints()->where('is_enabled', true)->count();
        $disabledCount = $gateway->dataPoints()->where('is_enabled', false)->count();

        $this->assertGreaterThan(0, $enabledCount);
        $this->assertGreaterThan(0, $disabledCount);

        // Step 6: User starts polling and verifies only enabled points are read
        $gateway->update(['is_active' => true]);

        // Create readings only for enabled data points
        $enabledDataPoints = $gateway->dataPoints()->where('is_enabled', true)->get();
        foreach ($enabledDataPoints as $dataPoint) {
            Reading::factory()->create([
                'data_point_id' => $dataPoint->id,
                'read_at' => now(),
            ]);
        }

        // Verify readings only exist for enabled data points
        $readingCount = Reading::whereIn('data_point_id', $enabledDataPoints->pluck('id'))->count();
        $this->assertEquals($enabledCount, $readingCount);

        // Step 7: User views live data filtered by group
        $liveDataResponse = $this->get('/admin/live-data?group=Meter_1');
        $liveDataResponse->assertStatus(200);
        $liveDataResponse->assertSee('Meter_1');
    }

    /** @test */
    public function critical_path_error_recovery_and_system_resilience()
    {
        // Setup: Create gateway with mixed success/failure scenario
        $gateway = Gateway::factory()->create([
            'name' => 'Resilience Test Gateway',
            'is_active' => true,
        ]);

        $dataPoints = DataPoint::factory()->count(10)->create([
            'gateway_id' => $gateway->id,
        ]);

        // Step 1: Simulate partial communication failures
        $this->mock(ModbusPollService::class, function ($mock) use ($gateway, $dataPoints) {
            foreach ($dataPoints as $index => $dataPoint) {
                // 70% success rate
                $success = ($index % 10) < 7;
                
                $mock->shouldReceive('readRegister')
                    ->with($gateway, $dataPoint)
                    ->andReturn(new \App\Services\ReadingResult(
                        success: $success,
                        rawValue: $success ? '[12345, 67890]' : null,
                        scaledValue: $success ? rand(100, 999) / 10 : null,
                        quality: $success ? 'good' : 'bad',
                        error: $success ? null : 'Modbus exception'
                    ));
            }
        });

        // Simulate polling with mixed results
        foreach ($dataPoints as $dataPoint) {
            $quality = rand(0, 9) < 7 ? 'good' : 'bad';
            Reading::factory()->create([
                'data_point_id' => $dataPoint->id,
                'quality' => $quality,
                'scaled_value' => $quality === 'good' ? rand(100, 999) / 10 : null,
                'read_at' => now(),
            ]);
        }

        // Step 2: User views dashboard and notices degraded performance
        $dashboardResponse = $this->get('/admin');
        $dashboardResponse->assertStatus(200);
        
        // Calculate expected success rate
        $totalReadings = Reading::count();
        $goodReadings = Reading::where('quality', 'good')->count();
        $expectedSuccessRate = ($goodReadings / $totalReadings) * 100;
        
        // Should show degraded success rate
        $this->assertLessThan(100, $expectedSuccessRate);

        // Step 3: User investigates specific gateway
        $gatewayDetailResponse = $this->get("/admin/gateways/{$gateway->id}");
        $gatewayDetailResponse->assertStatus(200);

        // Step 4: User views live data and sees quality indicators
        $liveDataResponse = $this->get('/admin/live-data');
        $liveDataResponse->assertStatus(200);
        $liveDataResponse->assertSee('good'); // Some readings should be good
        $liveDataResponse->assertSee('bad');  // Some readings should be bad

        // Step 5: System automatically handles failures gracefully
        // Create more readings with improving quality
        foreach ($dataPoints->take(5) as $dataPoint) {
            Reading::factory()->create([
                'data_point_id' => $dataPoint->id,
                'quality' => 'good',
                'scaled_value' => rand(100, 999) / 10,
                'read_at' => now()->addMinutes(1),
            ]);
        }

        // Step 6: User sees recovery in dashboard
        $recoveryDashboardResponse = $this->get('/admin');
        $recoveryDashboardResponse->assertStatus(200);

        // Step 7: User verifies system maintains data integrity
        $allReadings = Reading::all();
        
        // Verify no corrupted data
        foreach ($allReadings as $reading) {
            if ($reading->quality === 'good') {
                $this->assertNotNull($reading->scaled_value);
            } else {
                // Bad quality readings may have null values
                $this->assertEquals('bad', $reading->quality);
            }
        }

        // Step 8: User confirms system continues operating
        $finalLiveDataResponse = $this->get('/admin/live-data');
        $finalLiveDataResponse->assertStatus(200);
        
        // System should still be functional
        $this->assertGreaterThan(0, Reading::where('quality', 'good')->count());
    }
}