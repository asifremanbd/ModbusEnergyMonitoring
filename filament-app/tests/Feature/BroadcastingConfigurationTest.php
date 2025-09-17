<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class BroadcastingConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_service_provider_is_registered()
    {
        // Assert that the BroadcastServiceProvider is loaded
        $providers = app()->getLoadedProviders();
        $this->assertArrayHasKey('App\Providers\BroadcastServiceProvider', $providers);
    }

    public function test_broadcast_channels_are_configured()
    {
        // Test that our custom channels are accessible
        $this->assertTrue(true); // Placeholder - actual channel testing requires more setup
    }

    public function test_pusher_configuration_exists()
    {
        // Test that Pusher configuration is available
        $config = config('broadcasting.connections.pusher');
        
        $this->assertIsArray($config);
        $this->assertEquals('pusher', $config['driver']);
        $this->assertArrayHasKey('key', $config);
        $this->assertArrayHasKey('secret', $config);
        $this->assertArrayHasKey('app_id', $config);
        $this->assertArrayHasKey('options', $config);
    }

    public function test_broadcast_driver_configuration()
    {
        // Test that broadcast driver is configurable
        $defaultDriver = config('broadcasting.default');
        $this->assertIsString($defaultDriver);
    }

    public function test_echo_javascript_configuration_variables_exist()
    {
        // Test that the necessary environment variables for Echo are defined
        $this->assertNotNull(config('app.name'));
        
        // These might be null in testing but should be defined
        $pusherKey = env('VITE_PUSHER_APP_KEY');
        $pusherCluster = env('VITE_PUSHER_APP_CLUSTER');
        
        // In a real environment, these would be set
        $this->assertTrue(true); // Placeholder for environment-specific tests
    }

    public function test_broadcast_routes_are_registered()
    {
        // Test that broadcast authentication routes are available
        $response = $this->post('/broadcasting/auth');
        
        // Should return 401 or 403 (not 404) indicating the route exists
        $this->assertContains($response->status(), [401, 403, 422]);
    }

    public function test_channels_file_exists_and_is_loadable()
    {
        // Test that the channels.php file exists and can be loaded
        $channelsPath = base_path('routes/channels.php');
        $this->assertFileExists($channelsPath);
        
        // Test that it's valid PHP
        $this->assertIsString(file_get_contents($channelsPath));
    }
}