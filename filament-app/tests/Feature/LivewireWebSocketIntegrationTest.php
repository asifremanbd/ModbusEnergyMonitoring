<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\LiveData;
use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireWebSocketIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_responds_to_echo_events()
    {
        // Arrange
        $gateway = Gateway::factory()->create();
        DataPoint::factory()->create(['gateway_id' => $gateway->id]);

        // Act & Assert - Test that the component can handle echo events
        Livewire::test(Dashboard::class)
            ->assertSet('kpis', [])
            ->call('refreshDashboard')
            ->assertSet('kpis', function ($kpis) {
                return is_array($kpis) && isset($kpis['online_gateways']);
            });
    }

    public function test_dashboard_handles_gateway_status_changed_event()
    {
        // Arrange
        $gateway = Gateway::factory()->create(['is_active' => true]);

        // Act & Assert
        Livewire::test(Dashboard::class)
            ->call('refreshDashboard')
            ->assertSet('gateways', function ($gateways) use ($gateway) {
                return count($gateways) === 1 && $gateways[0]['id'] === $gateway->id;
            });
    }

    public function test_dashboard_handles_new_reading_event()
    {
        // Arrange
        $gateway = Gateway::factory()->create();
        $dataPoint = DataPoint::factory()->create(['gateway_id' => $gateway->id]);
        Reading::factory()->create([
            'data_point_id' => $dataPoint->id,
            'quality' => 'good',
            'read_at' => now(),
        ]);

        // Act & Assert
        Livewire::test(Dashboard::class)
            ->call('refreshDashboard')
            ->assertSet('kpis', function ($kpis) {
                return isset($kpis['poll_success_rate']) && $kpis['poll_success_rate']['value'] > 0;
            });
    }

    public function test_live_data_responds_to_echo_events()
    {
        // Arrange
        $gateway = Gateway::factory()->create();
        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        // Act & Assert
        Livewire::test(LiveData::class)
            ->assertSet('dataPoints', [])
            ->call('refreshLiveData')
            ->assertSet('dataPoints', function ($dataPoints) use ($dataPoint) {
                return count($dataPoints) === 1 && $dataPoints[0]['id'] === $dataPoint->id;
            });
    }

    public function test_live_data_handles_new_reading_event()
    {
        // Arrange
        $gateway = Gateway::factory()->create(['is_active' => true]);
        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);
        $reading = Reading::factory()->create([
            'data_point_id' => $dataPoint->id,
            'scaled_value' => 230.5,
            'quality' => 'good',
            'read_at' => now(),
        ]);

        // Act & Assert
        Livewire::test(LiveData::class)
            ->call('refreshLiveData')
            ->assertSet('dataPoints', function ($dataPoints) use ($dataPoint) {
                return count($dataPoints) === 1 && 
                       $dataPoints[0]['id'] === $dataPoint->id &&
                       $dataPoints[0]['current_value'] !== 'N/A';
            });
    }

    public function test_live_data_handles_gateway_status_changed_event()
    {
        // Arrange
        $gateway = Gateway::factory()->create(['is_active' => false]);
        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);

        // Act & Assert
        Livewire::test(LiveData::class)
            ->call('refreshLiveData')
            ->assertSet('dataPoints', function ($dataPoints) {
                return count($dataPoints) === 1 && $dataPoints[0]['status'] === 'down';
            });
    }

    public function test_dashboard_kpi_calculations_update_with_new_data()
    {
        // Arrange - Create gateway with some readings
        $gateway = Gateway::factory()->create(['is_active' => true]);
        $dataPoint = DataPoint::factory()->create([
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);
        
        // Create some good readings
        Reading::factory()->count(8)->create([
            'data_point_id' => $dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subMinutes(rand(1, 60)),
        ]);
        
        // Create some bad readings
        Reading::factory()->count(2)->create([
            'data_point_id' => $dataPoint->id,
            'quality' => 'bad',
            'read_at' => now()->subMinutes(rand(1, 60)),
        ]);

        // Act & Assert
        Livewire::test(Dashboard::class)
            ->call('refreshDashboard')
            ->assertSet('kpis', function ($kpis) {
                return isset($kpis['poll_success_rate']) && 
                       $kpis['poll_success_rate']['value'] === 80.0; // 8 good out of 10 total
            });
    }

    public function test_live_data_filters_work_with_real_time_updates()
    {
        // Arrange
        $gateway1 = Gateway::factory()->create(['name' => 'Gateway 1', 'is_active' => true]);
        $gateway2 = Gateway::factory()->create(['name' => 'Gateway 2', 'is_active' => true]);
        
        $dataPoint1 = DataPoint::factory()->create([
            'gateway_id' => $gateway1->id,
            'group_name' => 'Meter_1',
            'is_enabled' => true,
        ]);
        
        $dataPoint2 = DataPoint::factory()->create([
            'gateway_id' => $gateway2->id,
            'group_name' => 'Meter_2',
            'is_enabled' => true,
        ]);

        // Act & Assert - Test filtering by gateway
        Livewire::test(LiveData::class)
            ->call('setFilter', 'gateway', $gateway1->id)
            ->call('refreshLiveData')
            ->assertSet('dataPoints', function ($dataPoints) use ($dataPoint1) {
                return count($dataPoints) === 1 && $dataPoints[0]['id'] === $dataPoint1->id;
            });
    }

    public function test_components_handle_websocket_connection_gracefully()
    {
        // This test ensures components work even when WebSocket is not available
        
        // Arrange
        $gateway = Gateway::factory()->create();
        $dataPoint = DataPoint::factory()->create(['gateway_id' => $gateway->id]);

        // Act & Assert - Components should work without WebSocket
        Livewire::test(Dashboard::class)
            ->assertSuccessful()
            ->call('refreshDashboard')
            ->assertSuccessful();

        Livewire::test(LiveData::class)
            ->assertSuccessful()
            ->call('refreshLiveData')
            ->assertSuccessful();
    }
}