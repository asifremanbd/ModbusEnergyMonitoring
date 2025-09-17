<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResponsiveDesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'is_active' => true,
        ]);

        $this->dataPoint = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'label' => 'Test Data Point',
            'group_name' => 'Test Group',
        ]);

        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'scaled_value' => 123.45,
            'quality' => 'good',
        ]);
    }

    /** @test */
    public function dashboard_has_responsive_grid_classes()
    {
        $response = $this->get('/admin');

        $response->assertStatus(200);
        
        // Check for responsive grid classes on KPI tiles
        $response->assertSee('grid-cols-1 md:grid-cols-3');
        
        // Check for responsive gap classes
        $response->assertSee('gap-4 md:gap-6');
        
        // Check for responsive fleet status grid
        $response->assertSee('grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4');
    }

    /** @test */
    public function live_data_interface_has_responsive_layout()
    {
        $response = $this->get('/admin/live-data');

        $response->assertStatus(200);
        
        // Check for responsive header layout
        $response->assertSee('flex-col lg:flex-row');
        $response->assertSee('lg:items-center lg:justify-between');
        
        // Check for responsive filter controls
        $response->assertSee('filter-controls');
        $response->assertSee('flex-col sm:flex-row');
        
        // Check for responsive table overflow
        $response->assertSee('overflow-x-auto');
    }

    /** @test */
    public function css_contains_mobile_breakpoints()
    {
        $cssContent = file_get_contents(resource_path('css/filament/admin/theme.css'));

        // Check for extra small screens breakpoint
        $this->assertStringContainsString('@media (max-width: 475px)', $cssContent);
        
        // Check for small screens breakpoint
        $this->assertStringContainsString('@media (min-width: 476px) and (max-width: 640px)', $cssContent);
        
        // Check for medium screens breakpoint
        $this->assertStringContainsString('@media (min-width: 641px) and (max-width: 768px)', $cssContent);
        
        // Check for large screens breakpoint
        $this->assertStringContainsString('@media (min-width: 769px) and (max-width: 1024px)', $cssContent);
        
        // Check for extra large screens breakpoint
        $this->assertStringContainsString('@media (min-width: 1025px)', $cssContent);
    }

    /** @test */
    public function css_has_mobile_touch_target_improvements()
    {
        $cssContent = file_get_contents(resource_path('css/filament/admin/theme.css'));

        // Check for touch target improvements on mobile
        $this->assertStringContainsString('min-height: 44px', $cssContent);
        $this->assertStringContainsString('min-width: 44px', $cssContent);
        
        // Check for mobile-specific padding adjustments
        $this->assertStringContainsString('padding: 0.75rem', $cssContent);
        $this->assertStringContainsString('padding: 0.375rem', $cssContent);
    }

    /** @test */
    public function css_has_responsive_typography()
    {
        $cssContent = file_get_contents(resource_path('css/filament/admin/theme.css'));

        // Check for responsive font sizes
        $this->assertStringContainsString('font-size: 0.875rem', $cssContent);
        $this->assertStringContainsString('font-size: 0.75rem', $cssContent);
    }

    /** @test */
    public function css_has_print_styles()
    {
        $cssContent = file_get_contents(resource_path('css/filament/admin/theme.css'));

        // Check for print media query
        $this->assertStringContainsString('@media print', $cssContent);
        
        // Check for hiding interactive elements in print
        $this->assertStringContainsString('display: none !important', $cssContent);
        
        // Check for print-friendly table styles
        $this->assertStringContainsString('border-collapse: collapse', $cssContent);
        $this->assertStringContainsString('border: 1px solid #000000', $cssContent);
    }

    /** @test */
    public function css_has_high_contrast_mode_support()
    {
        $cssContent = file_get_contents(resource_path('css/filament/admin/theme.css'));

        // Check for high contrast media query
        $this->assertStringContainsString('@media (prefers-contrast: high)', $cssContent);
        
        // Check for high contrast focus indicators
        $this->assertStringContainsString('outline: 4px solid #000000', $cssContent);
        $this->assertStringContainsString('box-shadow: 0 0 0 2px #ffffff, 0 0 0 6px #000000', $cssContent);
    }

    /** @test */
    public function css_has_reduced_motion_support()
    {
        $cssContent = file_get_contents(resource_path('css/filament/admin/theme.css'));

        // Check for reduced motion media query
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $cssContent);
        
        // Check for animation duration overrides
        $this->assertStringContainsString('animation-duration: 0.01ms !important', $cssContent);
        $this->assertStringContainsString('transition-duration: 0.01ms !important', $cssContent);
    }

    /** @test */
    public function css_has_dark_mode_support()
    {
        $cssContent = file_get_contents(resource_path('css/filament/admin/theme.css'));

        // Check for dark mode media query
        $this->assertStringContainsString('@media (prefers-color-scheme: dark)', $cssContent);
        
        // Check for dark mode color variables
        $this->assertStringContainsString('--sidebar-bg: #0f172a', $cssContent);
        $this->assertStringContainsString('--content-bg: #1e293b', $cssContent);
    }

    /** @test */
    public function tailwind_config_has_custom_breakpoints()
    {
        $configContent = file_get_contents(base_path('tailwind.config.js'));

        // Check for extra small breakpoint
        $this->assertStringContainsString("'xs': '475px'", $configContent);
    }

    /** @test */
    public function tailwind_config_has_custom_colors()
    {
        $configContent = file_get_contents(base_path('tailwind.config.js'));

        // Check for custom color definitions
        $this->assertStringContainsString("'deep-navy': '#1e293b'", $configContent);
        $this->assertStringContainsString("'industrial-blue': '#3b82f6'", $configContent);
        $this->assertStringContainsString("'success-green': '#059669'", $configContent);
        $this->assertStringContainsString("'warning-orange': '#d97706'", $configContent);
        $this->assertStringContainsString("'danger-red': '#dc2626'", $configContent);
    }

    /** @test */
    public function dashboard_adapts_to_different_screen_sizes()
    {
        $response = $this->get('/admin');

        // Check for responsive classes that adapt layout
        $response->assertSee('fi-dashboard-tiles');
        $response->assertSee('grid-cols-1 md:grid-cols-3');
        
        // Check for responsive spacing
        $response->assertSee('gap-4 md:gap-6');
        $response->assertSee('space-y-6');
    }

    /** @test */
    public function filter_controls_stack_on_mobile()
    {
        $response = $this->get('/admin/live-data');

        // Check for filter controls responsive classes
        $response->assertSee('filter-controls');
        $response->assertSee('flex-col sm:flex-row');
        $response->assertSee('gap-3 flex-1');
    }

    /** @test */
    public function tables_have_horizontal_scroll_on_mobile()
    {
        $response = $this->get('/admin/live-data');

        // Check for horizontal scroll container
        $response->assertSee('overflow-x-auto');
        
        // Check for minimum table width
        $response->assertSee('min-w-full');
    }

    /** @test */
    public function buttons_have_adequate_touch_targets()
    {
        $response = $this->get('/admin/live-data');

        // Check for button classes that ensure adequate touch targets
        $response->assertSee('px-3 py-2');
        $response->assertSee('px-4 py-2');
        
        // Check for filter chip sizing
        $response->assertSee('filter-chip');
    }

    /** @test */
    public function gateway_cards_adapt_to_screen_size()
    {
        $response = $this->get('/admin');

        // Check for responsive grid on gateway cards
        $response->assertSee('grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4');
        
        // Check for responsive gap
        $response->assertSee('gap-4');
    }

    /** @test */
    public function text_remains_readable_at_different_sizes()
    {
        $response = $this->get('/admin');

        // Check for responsive text sizing classes
        $response->assertSee('text-sm');
        $response->assertSee('text-xs');
        $response->assertSee('text-2xl');
        
        // Check for truncation on small screens
        $response->assertSee('truncate');
    }

    /** @test */
    public function navigation_adapts_to_mobile()
    {
        $response = $this->get('/admin');

        // Check for responsive navigation classes
        $response->assertSee('fi-sidebar');
        $response->assertSee('fi-main');
    }

    /** @test */
    public function forms_are_mobile_friendly()
    {
        $response = $this->get('/admin/live-data');

        // Check for responsive form layouts
        $response->assertSee('flex-col lg:flex-row');
        $response->assertSee('min-w-0 flex-1');
        
        // Check for proper input sizing
        $response->assertSee('block w-full');
        $response->assertSee('rounded-md');
    }

    /** @test */
    public function spacing_adapts_to_screen_size()
    {
        $response = $this->get('/admin');

        // Check for responsive spacing classes
        $response->assertSee('space-y-6');
        $response->assertSee('gap-4');
        $response->assertSee('p-6');
        $response->assertSee('px-6 py-3');
    }
}