<?php

namespace Tests\Unit;

use App\Livewire\Dashboard;
use Tests\TestCase;

class DashboardIntegrationTest extends TestCase
{
    public function test_dashboard_component_can_be_instantiated()
    {
        $dashboard = new Dashboard();
        $this->assertInstanceOf(Dashboard::class, $dashboard);
    }

    public function test_dashboard_component_has_default_properties()
    {
        $dashboard = new Dashboard();
        
        // Check that properties are initialized as arrays
        $this->assertIsArray($dashboard->kpis);
        $this->assertIsArray($dashboard->gateways);
        $this->assertIsArray($dashboard->recentEvents);
        
        // Initially should be empty
        $this->assertEmpty($dashboard->kpis);
        $this->assertEmpty($dashboard->gateways);
        $this->assertEmpty($dashboard->recentEvents);
    }

    public function test_dashboard_render_method_returns_view()
    {
        $dashboard = new Dashboard();
        $view = $dashboard->render();
        
        $this->assertInstanceOf(\Illuminate\View\View::class, $view);
        $this->assertEquals('livewire.dashboard', $view->getName());
    }

    public function test_dashboard_methods_exist_and_callable()
    {
        $dashboard = new Dashboard();
        
        $this->assertTrue(method_exists($dashboard, 'mount'));
        $this->assertTrue(method_exists($dashboard, 'refreshDashboard'));
        $this->assertTrue(method_exists($dashboard, 'loadDashboardData'));
        $this->assertTrue(method_exists($dashboard, 'render'));
        
        $this->assertTrue(is_callable([$dashboard, 'mount']));
        $this->assertTrue(is_callable([$dashboard, 'refreshDashboard']));
        $this->assertTrue(is_callable([$dashboard, 'loadDashboardData']));
        $this->assertTrue(is_callable([$dashboard, 'render']));
    }

    public function test_dashboard_view_template_structure()
    {
        $viewPath = resource_path('views/livewire/dashboard.blade.php');
        $content = file_get_contents($viewPath);
        
        // Test for main structure
        $this->assertStringContainsString('<div class="space-y-6"', $content);
        $this->assertStringContainsString('wire:poll.30s="refreshDashboard"', $content);
        
        // Test for KPI section
        $this->assertStringContainsString('grid grid-cols-1 md:grid-cols-3', $content);
        $this->assertStringContainsString('Online Gateways', $content);
        $this->assertStringContainsString('Poll Success Rate', $content);
        $this->assertStringContainsString('Average Latency', $content);
        
        // Test for Fleet Status section
        $this->assertStringContainsString('Fleet Status', $content);
        $this->assertStringContainsString('No gateways configured', $content);
        
        // Test for Recent Events section
        $this->assertStringContainsString('Recent Events', $content);
        $this->assertStringContainsString('No recent events to display', $content);
        
        // Test for responsive classes
        $this->assertStringContainsString('sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4', $content);
        
        // Test for accessibility features
        $this->assertStringContainsString('aria-labelledby', $content);
        $this->assertStringContainsString('aria-hidden="true"', $content);
        $this->assertStringContainsString('role="img"', $content);
        $this->assertStringContainsString('role="list"', $content);
    }

    public function test_filament_dashboard_page_integration()
    {
        $pageViewPath = resource_path('views/filament/pages/dashboard.blade.php');
        $content = file_get_contents($pageViewPath);
        
        // Should use Filament page wrapper
        $this->assertStringContainsString('<x-filament-panels::page>', $content);
        $this->assertStringContainsString('</x-filament-panels::page>', $content);
        
        // Should include Livewire component
        $this->assertStringContainsString('@livewire(\'dashboard\')', $content);
    }

    public function test_dashboard_css_classes_for_status_indicators()
    {
        $viewPath = resource_path('views/livewire/dashboard.blade.php');
        $content = file_get_contents($viewPath);
        
        // Test for status color classes
        $this->assertStringContainsString('bg-green-100', $content);
        $this->assertStringContainsString('bg-yellow-100', $content);
        $this->assertStringContainsString('bg-red-100', $content);
        $this->assertStringContainsString('text-green-600', $content);
        $this->assertStringContainsString('text-yellow-600', $content);
        $this->assertStringContainsString('text-red-600', $content);
        
        // Test for badge classes
        $this->assertStringContainsString('bg-green-100 text-green-800', $content);
        $this->assertStringContainsString('bg-yellow-100 text-yellow-800', $content);
        $this->assertStringContainsString('bg-red-100 text-red-800', $content);
    }

    public function test_dashboard_empty_states()
    {
        $viewPath = resource_path('views/livewire/dashboard.blade.php');
        $content = file_get_contents($viewPath);
        
        // Test for empty state messages
        $this->assertStringContainsString('No gateways configured', $content);
        $this->assertStringContainsString('Get started by adding your first Teltonika gateway', $content);
        $this->assertStringContainsString('No recent events to display', $content);
        
        // Test for empty state actions
        $this->assertStringContainsString('Add Gateway', $content);
        $this->assertStringContainsString('/admin/gateways/create', $content);
    }

    public function test_dashboard_sparkline_implementation()
    {
        $viewPath = resource_path('views/livewire/dashboard.blade.php');
        $content = file_get_contents($viewPath);
        
        // Test for sparkline structure
        $this->assertStringContainsString('Activity (last hour)', $content);
        $this->assertStringContainsString('sparkline_data', $content);
        $this->assertStringContainsString('Activity sparkline chart', $content);
        $this->assertStringContainsString('bg-blue-200', $content);
    }
}