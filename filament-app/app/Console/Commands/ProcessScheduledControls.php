<?php

namespace App\Console\Commands;

use App\Services\SchedulingService;
use Illuminate\Console\Command;

class ProcessScheduledControls extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'schedule:process-controls
                          {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Process scheduled control device state changes';

    /**
     * Execute the console command.
     */
    public function handle(SchedulingService $schedulingService): int
    {
        $this->info('Processing scheduled control device changes...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
            
            $changes = $schedulingService->getDevicesNeedingStateChange();
            
            if (empty($changes)) {
                $this->info('No devices need state changes at this time.');
                return 0;
            }

            $this->info('Devices that would be changed:');
            
            foreach ($changes as $change) {
                $device = $change['device'];
                $action = $change['target_state'] ? 'ENABLE' : 'DISABLE';
                $reason = $change['reason'] === 'schedule_start' ? 'Schedule Start' : 'Schedule End';
                
                $this->line("  - {$device->display_label} → {$action} ({$reason})");
            }
            
            return 0;
        }

        // Apply the changes
        $results = $schedulingService->applyScheduledChanges();

        if (empty($results)) {
            $this->info('No devices needed state changes at this time.');
            return 0;
        }

        $successful = 0;
        $failed = 0;

        foreach ($results as $result) {
            if ($result['success']) {
                $successful++;
                $action = $result['action'] === 'enabled' ? 'ENABLED' : 'DISABLED';
                $this->info("✓ {$result['device_label']} → {$action}");
            } else {
                $failed++;
                $this->error("✗ {$result['device_label']} → FAILED: {$result['error']}");
            }
        }

        $this->newLine();
        $this->info("Summary: {$successful} successful, {$failed} failed");

        return $failed > 0 ? 1 : 0;
    }
}