<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Services\AccessibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessibilityTest extends TestCase
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
            'application' => 'monitoring',
            'unit' => 'kWh',
            'load_type' => 'power',
        ]);

        Reading::factory()->create([
            'data_point_id' => $this->dataPoint->id,
            'scaled_value' => 123.45,
            'quality' => 'good',
        ]);
    }

    /** @test */
    public function dashboard_has_proper_accessibility_structure()
    {
        $response = $this->get('/admin');

        $response->assertStatus(200);
        
        // Check for skip link
        $response->assertSee('Skip to main content');
        
        // Check for proper ARIA landmarks
        $response->assertSee('role="main"');
        $response->assertSee('aria-label="Gateway monitoring dashboard"');
        
        // Check for proper heading structure
        $response->assertSee('id="kpi-heading"');
        $response->assertSee('id="fleet-status-heading"');
        $response->assertSee('id="recent-events-heading"');
        
        // Check for live regions
        $response->assertSee('aria-live="polite"');
    }

    /** @test */
    public function live_data_interface_has_accessibility_features()
    {
        $response = $this->get('/admin/live-data');

        $response->assertStatus(200);
        
        // Check for skip link
        $response->assertSee('Skip to live data content');
        
        // Check for proper table structure
        $response->assertSee('role="table"');
        $response->assertSee('scope="col"');
        $response->assertSee('role="columnheader"');
        
        // Check for filter accessibility
        $response->assertSee('role="search"');
        $response->assertSee('aria-describedby');
        
        // Check for proper button labels
        $response->assertSee('aria-label="Toggle between comfortable and compact table view"');
        $response->assertSee('aria-pressed');
    }

    /** @test */
    public function status_indicators_have_proper_aria_labels()
    {
        $onlineLabel = AccessibilityService::getStatusAriaLabel('online');
        $offlineLabel = AccessibilityService::getStatusAriaLabel('offline');
        $unknownLabel = AccessibilityService::getStatusAriaLabel('unknown');

        $this->assertEquals('Gateway is online and responding', $onlineLabel);
        $this->assertEquals('Gateway is offline or not responding', $offlineLabel);
        $this->assertEquals('Gateway status is unknown', $unknownLabel);
    }

    /** @test */
    public function color_contrast_meets_wcag_aa_requirements()
    {
        $colors = AccessibilityService::getContrastColors();

        foreach ($colors as $colorSet) {
            $this->assertGreaterThanOrEqual(4.5, $colorSet['contrast_ratio']);
        }
    }

    /** @test */
    public function keyboard_navigation_attributes_are_generated_correctly()
    {
        $attributes = AccessibilityService::getKeyboardNavAttributes(0, 'button', 'Test button');

        $this->assertEquals(0, $attributes['tabindex']);
        $this->assertEquals('button', $attributes['role']);
        $this->assertEquals('Test button', $attributes['aria-label']);
    }

    /** @test */
    public function table_header_attributes_are_generated_correctly()
    {
        $sortableAttributes = AccessibilityService::getTableHeaderAttributes('Gateway', true);
        $nonSortableAttributes = AccessibilityService::getTableHeaderAttributes('Status', false);

        $this->assertEquals('col', $sortableAttributes['scope']);
        $this->assertEquals('columnheader', $sortableAttributes['role']);
        $this->assertEquals('Sort by Gateway', $sortableAttributes['aria-label']);
        $this->assertEquals('0', $sortableAttributes['tabindex']);

        $this->assertEquals('col', $nonSortableAttributes['scope']);
        $this->assertEquals('columnheader', $nonSortableAttributes['role']);
        $this->assertEquals('Status column header', $nonSortableAttributes['aria-label']);
        $this->assertArrayNotHasKey('tabindex', $nonSortableAttributes);
    }

    /** @test */
    public function form_control_attributes_include_proper_aria_labels()
    {
        $attributes = AccessibilityService::getFormControlAttributes(
            'Gateway Name',
            true,
            'gateway-help',
            'gateway-error'
        );

        $this->assertEquals('Gateway Name', $attributes['aria-label']);
        $this->assertEquals('true', $attributes['aria-required']);
        $this->assertEquals('true', $attributes['aria-invalid']);
        $this->assertEquals('gateway-help gateway-error', $attributes['aria-describedby']);
    }

    /** @test */
    public function live_region_attributes_are_generated_correctly()
    {
        $politeAttributes = AccessibilityService::getLiveRegionAttributes('polite', false);
        $assertiveAttributes = AccessibilityService::getLiveRegionAttributes('assertive', true);

        $this->assertEquals('polite', $politeAttributes['aria-live']);
        $this->assertEquals('false', $politeAttributes['aria-atomic']);

        $this->assertEquals('assertive', $assertiveAttributes['aria-live']);
        $this->assertEquals('true', $assertiveAttributes['aria-atomic']);
    }

    /** @test */
    public function progress_attributes_are_generated_correctly()
    {
        $attributes = AccessibilityService::getProgressAttributes(75, 0, 100, 'Loading progress');

        $this->assertEquals('progressbar', $attributes['role']);
        $this->assertEquals(75, $attributes['aria-valuenow']);
        $this->assertEquals(0, $attributes['aria-valuemin']);
        $this->assertEquals(100, $attributes['aria-valuemax']);
        $this->assertEquals('Loading progress', $attributes['aria-label']);
    }

    /** @test */
    public function expandable_attributes_work_correctly()
    {
        $expandedAttributes = AccessibilityService::getExpandableAttributes(true, 'content-panel');
        $collapsedAttributes = AccessibilityService::getExpandableAttributes(false);

        $this->assertEquals('true', $expandedAttributes['aria-expanded']);
        $this->assertEquals('content-panel', $expandedAttributes['aria-controls']);

        $this->assertEquals('false', $collapsedAttributes['aria-expanded']);
        $this->assertArrayNotHasKey('aria-controls', $collapsedAttributes);
    }

    /** @test */
    public function skip_link_html_is_generated_correctly()
    {
        $skipLink = AccessibilityService::getSkipLinkHtml('#main-content', 'Skip to main content');

        $this->assertStringContainsString('href="#main-content"', $skipLink);
        $this->assertStringContainsString('class="skip-link"', $skipLink);
        $this->assertStringContainsString('Skip to main content', $skipLink);
    }

    /** @test */
    public function contrast_ratio_calculation_works_correctly()
    {
        // Test with known WCAG AA compliant colors
        $this->assertTrue(AccessibilityService::meetsContrastRequirement('#000000', '#ffffff')); // Black on white
        $this->assertTrue(AccessibilityService::meetsContrastRequirement('#ffffff', '#000000')); // White on black
        
        // Test with colors that don't meet requirements
        $this->assertFalse(AccessibilityService::meetsContrastRequirement('#ffffff', '#f0f0f0')); // Light gray on white
    }

    /** @test */
    public function responsive_image_attributes_are_generated()
    {
        $attributes = AccessibilityService::getResponsiveImageAttributes(
            'Gateway status chart',
            'Chart showing gateway performance over time'
        );

        $this->assertEquals('Gateway status chart', $attributes['alt']);
        $this->assertEquals('lazy', $attributes['loading']);
        $this->assertStringContainsString('img-caption-', $attributes['aria-describedby']);
    }

    /** @test */
    public function landmark_attributes_are_generated_correctly()
    {
        $navigationAttributes = AccessibilityService::getLandmarkAttributes('navigation', 'Main navigation');
        $mainAttributes = AccessibilityService::getLandmarkAttributes('main');

        $this->assertEquals('navigation', $navigationAttributes['role']);
        $this->assertEquals('Main navigation', $navigationAttributes['aria-label']);

        $this->assertEquals('main', $mainAttributes['role']);
        $this->assertArrayNotHasKey('aria-label', $mainAttributes);
    }

    /** @test */
    public function dashboard_kpi_tiles_have_live_regions()
    {
        $response = $this->get('/admin');

        // Check that KPI tiles have live region attributes for real-time updates
        $response->assertSee('aria-live="polite"');
        $response->assertSee('aria-atomic="false"');
    }

    /** @test */
    public function gateway_status_cards_have_proper_keyboard_navigation()
    {
        $response = $this->get('/admin');

        // Check for keyboard focusable elements
        $response->assertSee('keyboard-focusable');
        $response->assertSee('tabindex="0"');
        $response->assertSee('role="listitem"');
    }

    /** @test */
    public function filter_chips_have_proper_accessibility_attributes()
    {
        // Create a gateway with data points to ensure filters are available
        $response = $this->get('/admin/live-data');

        $response->assertStatus(200);
        
        // Check for filter accessibility
        $response->assertSee('role="search"');
        $response->assertSee('aria-label="Data Filters"');
    }

    /** @test */
    public function empty_states_have_proper_messaging()
    {
        // Test with no gateways
        Gateway::query()->delete();
        
        $response = $this->get('/admin');

        $response->assertSee('No gateways configured');
        $response->assertSee('Get started by adding your first Teltonika gateway');
        $response->assertSee('aria-label="Add your first gateway to start monitoring"');
    }

    /** @test */
    public function data_table_has_proper_structure_and_headers()
    {
        $response = $this->get('/admin/live-data');

        // Check for proper table structure
        $response->assertSee('role="table"');
        $response->assertSee('aria-label="Live data readings from gateways"');
        
        // Check for sticky headers
        $response->assertSee('sticky top-0');
        
        // Check for column headers with proper scope
        $response->assertSee('scope="col"');
        $response->assertSee('role="columnheader"');
    }
}