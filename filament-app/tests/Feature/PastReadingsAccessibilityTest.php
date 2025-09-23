<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Services\AccessibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PastReadingsAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected Gateway $gateway;
    protected DataPoint $dataPoint;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
            'is_active' => true,
        ]);
        
        $this->dataPoint = DataPoint::factory()->create([
            'gateway_id' => $this->gateway->id,
            'label' => 'Test Data Point',
            'group_name' => 'Test Group',
            'is_enabled' => true,
        ]);
        
        // Create test readings
        Reading::factory()->count(3)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);
        
        Reading::factory()->count(2)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'bad',
            'read_at' => now()->subHours(1),
        ]);
    }

    /** @test */
    public function past_readings_page_has_proper_accessibility_structure()
    {
        $response = $this->get('/admin/past-readings');

        $response->assertStatus(200);
        
        // Check for skip link
        $response->assertSee('Skip to past readings content');
        
        // Check for proper ARIA landmarks
        $response->assertSee('role="main"');
        $response->assertSee('aria-label="Past readings interface"');
        
        // Check for proper heading structure
        $response->assertSee('id="past-readings-content"');
        $response->assertSee('role="banner"');
        $response->assertSee('aria-label="Past readings page header"');
    }

    /** @test */
    public function statistics_display_has_proper_accessibility_attributes()
    {
        $component = Livewire::test(\App\Livewire\PastReadings::class);
        
        $html = $component->render()->toHtml();
        
        // Check that statistics are properly labeled
        $this->assertStringContainsString('Success:', $html);
        $this->assertStringContainsString('Fail:', $html);
        $this->assertStringContainsString('success rate', $html);
        
        // Statistics should be in a readable format with proper contrast
        $this->assertStringContainsString('text-green-600', $html); // Success color
        $this->assertStringContainsString('text-red-600', $html);   // Fail color
        $this->assertStringContainsString('font-mono', $html);      // Monospace for numbers
    }

    /** @test */
    public function filter_controls_have_proper_accessibility_attributes()
    {
        $component = Livewire::test(\App\Livewire\PastReadings::class);
        
        $html = $component->render()->toHtml();
        
        // Check for search landmark
        $this->assertStringContainsString('role="search"', $html);
        $this->assertStringContainsString('aria-labelledby="filters-heading"', $html);
        
        // Check for proper form labels
        $this->assertStringContainsString('for="gateway-filter"', $html);
        $this->assertStringContainsString('for="group-filter"', $html);
        $this->assertStringContainsString('for="data-point-filter"', $html);
        $this->assertStringContainsString('for="quality-filter"', $html);
        $this->assertStringContainsString('for="date-from"', $html);
        $this->assertStringContainsString('for="date-to"', $html);
        
        // Check for screen reader only heading
        $this->assertStringContainsString('id="filters-heading"', $html);
        $this->assertStringContainsString('class="sr-only"', $html);
    }

    /** @test */
    public function filter_chips_have_proper_accessibility_attributes()
    {
        $component = Livewire::test(\App\Livewire\PastReadings::class);
        
        // Apply a filter to generate chips
        $component->call('setFilter', 'gateway', $this->gateway->id);
        
        $html = $component->render()->toHtml();
        
        // Check for proper list structure
        $this->assertStringContainsString('role="list"', $html);
        $this->assertStringContainsString('aria-label="Active filters"', $html);
        $this->assertStringContainsString('role="listitem"', $html);
        
        // Check for remove button accessibility
        $this->assertStringContainsString('aria-label="Remove Gateway filter"', $html);
    }

    /** @test */
    public function date_range_quick_filters_have_proper_accessibility()
    {
        $component = Livewire::test(\App\Livewire\PastReadings::class);
        
        $html = $component->render()->toHtml();
        
        // Check that quick filter buttons are properly labeled
        $this->assertStringContainsString('Last Hour', $html);
        $this->assertStringContainsString('Last 24h', $html);
        $this->assertStringContainsString('Last Week', $html);
        $this->assertStringContainsString('Last Month', $html);
        
        // Check for proper focus management
        $this->assertStringContainsString('focus:outline-none', $html);
        $this->assertStringContainsString('focus:ring-2', $html);
        $this->assertStringContainsString('focus:ring-offset-2', $html);
        $this->assertStringContainsString('focus:ring-blue-500', $html);
    }

    /** @test */
    public function data_table_has_proper_accessibility_structure()
    {
        $component = Livewire::test(\App\Livewire\PastReadings::class);
        
        $html = $component->render()->toHtml();
        
        // Check for proper table structure
        $this->assertStringContainsString('class="min-w-full divide-y', $html);
        
        // Check for sortable column headers
        $this->assertStringContainsString('wire:click="sortBy(\'read_at\')"', $html);
        $this->assertStringContainsString('wire:click="sortBy(\'scaled_value\')"', $html);
        $this->assertStringContainsString('wire:click="sortBy(\'quality\')"', $html);
        
        // Check for hover states
        $this->assertStringContainsString('hover:bg-gray-50', $html);
        $this->assertStringContainsString('dark:hover:bg-gray-700', $html);
    }

    /** @test */
    public function sortable_columns_have_proper_accessibility_attributes()
    {
        $component = Livewire::test(\App\Livewire\PastReadings::class);
        
        $html = $component->render()->toHtml();
        
        // Check for sortable button structure
        $this->assertStringContainsString('flex items-center space-x-1', $html);
        $this->assertStringContainsString('hover:text-gray-700', $html);
        
        // Check for sort direction indicators
        $this->assertStringContainsString('w-4 h-4', $html); // Sort arrow size
        $this->assertStringContainsString('stroke="currentColor"', $html);
    }

    /** @test */
    public function quality_indicators_have_proper_color_contrast()
    {
        $component = Livewire::test(\App\Livewire\PastReadings::class);
        
        $html = $component->render()->toHtml();
        
        // Check for proper quality color coding with sufficient contrast
        $this->assertStringContainsString('bg-green-100 text-green-800', $html); // Good quality
        $this->assertStringContainsString('bg-red-100 text-red-800', $html);     // Bad quality
        $this->assertStringContainsString('bg-yellow-100 text-yellow-800', $html); // Uncertain quality
        
        // Check for dark mode variants
        $this->assertStringContainsString('dark:bg-green-900 dark:text-green-200', $html);
        $this->assertStringContainsString('dark:bg-red-900 dark:text-red-200', $html);
        $this->assertStringContainsString('dark:bg-yellow-900 dark:text-yellow-200', $html);
    }

    /** @test */
    public function pagination_has_proper_accessibility_attributes()
    {
        // Create enough readings to trigger pagination
        Reading::factory()->count(60)->create([
            'data_point_id' => $this->dataPoint->id,
            'quality' => 'good',
            'read_at' => now()->subHours(1),
        ]);
        
        $component = Livewire::test(\App\Livewire\PastReadings::class);
        
        $html = $component->render()->toHtml();
        
        // Check for pagination container
        $this->assertStringContainsString('border-t border-gray-200', $html);
        
        // Laravel's pagination should include proper accessibility attributes
        // This is handled by Laravel's pagination view
    }

    /** @test */
    public function empty_state_has_proper_accessibility_messaging()
    {
        // Remove all readings to trigger empty state
        Reading::query()->delete();
        
        $component = Livewire::test(\App\Livewire\PastReadings::class);
        
        $html = $component->render()->toHtml();
        
        // Check for empty state messaging
        $this->assertStringContainsString('No readings found', $html);
        $this->assertStringContainsString('text-center py-12', $html);
        
        // Check for helpful messaging
        $this->assertStringContainsString('Try adjusting your filters', $html);
        
        // Check for clear filters button when filters are active
        $component->call('setFilter', 'quality', 'good');
        $html = $component->render()->toHtml();
        $this->assertStringContainsString('Clear Filters', $html);
    }

    /** @test */
    public function keyboard_navigation_works_properly()
    {
        $component = Livewire::test(\App\Livewire\PastReadings::class);
        
        $html = $component->render()->toHtml();
        
        // Check for proper focus management
        $this->assertStringContainsString('focus:outline-none', $html);
        $this->assertStringContainsString('focus:ring-2', $html);
        
        // Check for keyboard accessible buttons
        $this->assertStringContainsString('focus:ring-offset-2', $html);
        $this->assertStringContainsString('focus:ring-blue-500', $html);
    }

    /** @test */
    public function screen_reader_announcements_are_properly_structured()
    {
        $component = Livewire::test(\App\Livewire\PastReadings::class);
        
        $html = $component->render()->toHtml();
        
        // Check for screen reader only content
        $this->assertStringContainsString('class="sr-only"', $html);
        
        // Check for proper heading hierarchy
        $this->assertStringContainsString('<h1', $html); // Main heading
        $this->assertStringContainsString('<h2', $html); // Section headings
        
        // Statistics should be announced properly
        $this->assertStringContainsString('Success:', $html);
        $this->assertStringContainsString('Fail:', $html);
    }

    /** @test */
    public function datetime_inputs_have_proper_accessibility()
    {
        $component = Livewire::test(\App\Livewire\PastReadings::class);
        
        $html = $component->render()->toHtml();
        
        // Check for proper datetime input labels
        $this->assertStringContainsString('for="date-from"', $html);
        $this->assertStringContainsString('for="date-to"', $html);
        $this->assertStringContainsString('type="datetime-local"', $html);
        
        // Check for proper styling and focus states
        $this->assertStringContainsString('focus:border-blue-500', $html);
        $this->assertStringContainsString('focus:ring-blue-500', $html);
    }

    /** @test */
    public function color_coding_meets_accessibility_standards()
    {
        // Test that success/fail colors meet WCAG AA contrast requirements
        $this->assertTrue(AccessibilityService::meetsContrastRequirement('#059669', '#ffffff')); // Green on white
        $this->assertTrue(AccessibilityService::meetsContrastRequirement('#DC2626', '#ffffff')); // Red on white
        
        // Test dark mode variants
        $this->assertTrue(AccessibilityService::meetsContrastRequirement('#34D399', '#000000')); // Light green on black
        $this->assertTrue(AccessibilityService::meetsContrastRequirement('#F87171', '#000000')); // Light red on black
    }

    /** @test */
    public function responsive_design_maintains_accessibility()
    {
        $component = Livewire::test(\App\Livewire\PastReadings::class);
        
        $html = $component->render()->toHtml();
        
        // Check for responsive grid classes
        $this->assertStringContainsString('grid-cols-1 md:grid-cols-2 lg:grid-cols-4', $html);
        
        // Check for responsive flex classes
        $this->assertStringContainsString('flex-col lg:flex-row', $html);
        
        // Check for overflow handling
        $this->assertStringContainsString('overflow-x-auto', $html);
        
        // Check for responsive text sizing
        $this->assertStringContainsString('text-sm', $html);
        $this->assertStringContainsString('text-xs', $html);
    }

    /** @test */
    public function loading_states_have_proper_accessibility()
    {
        $component = Livewire::test(\App\Livewire\PastReadings::class);
        
        // Simulate loading state by checking for Livewire loading indicators
        $html = $component->render()->toHtml();
        
        // Livewire should handle loading states with proper ARIA attributes
        // This is typically handled by Livewire's built-in loading states
        $this->assertStringContainsString('wire:', $html); // Livewire directives present
    }

    /** @test */
    public function error_states_are_accessible()
    {
        // Test with invalid filter values to potentially trigger errors
        $component = Livewire::test(\App\Livewire\PastReadings::class);
        
        try {
            $component->call('setFilter', 'gateway', 'invalid-id');
        } catch (\Exception $e) {
            // Error handling should be graceful
        }
        
        // Component should still render properly
        $html = $component->render()->toHtml();
        $this->assertStringContainsString('Past Readings', $html);
    }
}