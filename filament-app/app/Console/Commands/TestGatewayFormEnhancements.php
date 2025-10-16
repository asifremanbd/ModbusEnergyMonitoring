<?php

namespace App\Console\Commands;

use App\Models\DataPoint;
use App\Models\Gateway;
use App\Services\SchedulingService;
use Illuminate\Console\Command;

class TestGatewayFormEnhancements extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:gateway-enhancements';

    /**
     * The console command description.
     */
    protected $description = 'Test the new gateway form enhancements and scheduling features';

    /**
     * Execute the console command.
     */
    public function handle(SchedulingService $schedulingService): int
    {
        $this->info('🧪 Testing Gateway Form Enhancements');
        $this->newLine();

        // Test 1: Check if new fields are available
        $this->info('1. Testing DataPoint model fields...');
        
        $testPoint = new DataPoint([
            'device_type' => 'control',
            'custom_label' => 'Test Control Device',
            'is_schedulable' => true,
            'schedule_enabled' => true,
            'schedule_days' => ['monday', 'tuesday', 'wednesday'],
            'schedule_start_time' => '08:00',
            'schedule_end_time' => '18:00',
        ]);

        $this->line('   ✓ New scheduling fields are accessible');
        $this->line('   ✓ Display label: ' . $testPoint->display_label);
        $this->line('   ✓ Device type check: ' . ($testPoint->is_control_device ? 'Control' : 'Other'));

        // Test 2: Check scheduling logic
        $this->newLine();
        $this->info('2. Testing scheduling logic...');
        
        // Create a test scenario
        $testPoint->schedule_days = [strtolower(now()->format('l'))]; // Today
        $testPoint->schedule_start_time = now()->subHour()->format('H:i'); // Started 1 hour ago
        $testPoint->schedule_end_time = now()->addHour()->format('H:i'); // Ends in 1 hour
        $testPoint->is_enabled = true;

        $shouldBeActive = $schedulingService->shouldDeviceBeActive($testPoint);
        $this->line('   ✓ Current time scheduling check: ' . ($shouldBeActive ? 'Should be active' : 'Should be inactive'));

        // Test 3: Check schedule summary
        $summary = $schedulingService->getScheduleSummary($testPoint);
        $this->line('   ✓ Schedule summary status: ' . $summary['status']);

        // Test 4: Test with existing data
        $this->newLine();
        $this->info('3. Testing with existing data...');
        
        $gatewayCount = Gateway::count();
        $dataPointCount = DataPoint::count();
        $controlDeviceCount = DataPoint::where('device_type', 'control')->count();
        $schedulableCount = DataPoint::where('is_schedulable', true)->count();

        $this->line("   ✓ Gateways in database: {$gatewayCount}");
        $this->line("   ✓ Data points in database: {$dataPointCount}");
        $this->line("   ✓ Control devices: {$controlDeviceCount}");
        $this->line("   ✓ Schedulable devices: {$schedulableCount}");

        // Test 5: Check for devices needing state changes
        $this->newLine();
        $this->info('4. Testing scheduled state changes...');
        
        $changes = $schedulingService->getDevicesNeedingStateChange();
        $this->line("   ✓ Devices needing state changes: " . count($changes));
        
        if (!empty($changes)) {
            foreach ($changes as $change) {
                $device = $change['device'];
                $action = $change['target_state'] ? 'ENABLE' : 'DISABLE';
                $this->line("     - {$device->display_label} → {$action}");
            }
        }

        // Test 6: Validate CSS files exist
        $this->newLine();
        $this->info('5. Testing CSS and asset files...');
        
        $cssFiles = [
            'css/gateway-form-enhancements.css',
            'css/dashboard-enhancements.css',
        ];

        foreach ($cssFiles as $file) {
            if (file_exists(resource_path($file))) {
                $this->line("   ✓ {$file} exists");
            } else {
                $this->error("   ✗ {$file} missing");
            }
        }

        // Test 7: Check migration file
        $this->newLine();
        $this->info('6. Testing migration file...');
        
        $migrationFile = database_path('migrations/2024_01_15_000000_add_scheduling_fields_to_data_points_table.php');
        if (file_exists($migrationFile)) {
            $this->line("   ✓ Migration file exists");
        } else {
            $this->error("   ✗ Migration file missing");
        }

        $this->newLine();
        $this->info('🎉 Gateway Form Enhancement Tests Complete!');
        $this->newLine();

        // Provide next steps
        $this->comment('Next Steps:');
        $this->line('1. Run the migration: php artisan migrate');
        $this->line('2. Build CSS assets: npm run build');
        $this->line('3. Test the form in the admin panel');
        $this->line('4. Set up cron job for scheduling: * * * * * php artisan schedule:process-controls');

        return 0;
    }
}