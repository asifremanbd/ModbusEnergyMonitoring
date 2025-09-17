<?php

namespace Tests\Unit;

use App\Livewire\Dashboard;
use Tests\TestCase;

class DashboardComponentStructureTest extends TestCase
{
    public function test_dashboard_component_exists()
    {
        $this->assertTrue(class_exists(Dashboard::class));
    }

    public function test_dashboard_component_has_required_methods()
    {
        $reflection = new \ReflectionClass(Dashboard::class);
        
        $this->assertTrue($reflection->hasMethod('mount'));
        $this->assertTrue($reflection->hasMethod('refreshDashboard'));
        $this->assertTrue($reflection->hasMethod('loadDashboardData'));
        $this->assertTrue($reflection->hasMethod('render'));
    }

    public function test_dashboard_component_has_required_properties()
    {
        $reflection = new \ReflectionClass(Dashboard::class);
        
        $this->assertTrue($reflection->hasProperty('kpis'));
        $this->assertTrue($reflection->hasProperty('gateways'));
        $this->assertTrue($reflection->hasProperty('recentEvents'));
    }

    public function test_dashboard_view_file_exists()
    {
        $viewPath = resource_path('views/livewire/dashboard.blade.php');
        $this->assertFileExists($viewPath);
    }

    public function test_dashboard_view_contains_required_elements()
    {
        $viewContent = file_get_contents(resource_path('views/livewire/dashboard.blade.php'));
        
        // Check for KPI tiles
        $this->assertStringContainsString('Online Gateways', $viewContent);
        $this->assertStringContainsString('Poll Success Rate', $viewContent);
        $this->assertStringContainsString('Average Latency', $viewContent);
        
        // Check for fleet status
        $this->assertStringContainsString('Fleet Status', $viewContent);
        
        // Check for recent events
        $this->assertStringContainsString('Recent Events', $viewContent);
        
        // Check for responsive classes
        $this->assertStringContainsString('grid-cols-1 md:grid-cols-3', $viewContent);
        $this->assertStringContainsString('sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4', $viewContent);
        
        // Check for accessibility features
        $this->assertStringContainsString('aria-labelledby', $viewContent);
        $this->assertStringContainsString('aria-hidden', $viewContent);
        $this->assertStringContainsString('role=', $viewContent);
        
        // Check for auto-refresh
        $this->assertStringContainsString('wire:poll.30s="refreshDashboard"', $viewContent);
    }

    public function test_dashboard_page_uses_livewire_component()
    {
        $pageViewPath = resource_path('views/filament/pages/dashboard.blade.php');
        $this->assertFileExists($pageViewPath);
        
        $pageContent = file_get_contents($pageViewPath);
        $this->assertStringContainsString('@livewire(\'dashboard\')', $pageContent);
    }

    public function test_dashboard_component_extends_livewire_component()
    {
        $reflection = new \ReflectionClass(Dashboard::class);
        $parentClass = $reflection->getParentClass();
        
        $this->assertEquals('Livewire\Component', $parentClass->getName());
    }
}