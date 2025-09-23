<?php

namespace App\Console\Commands;

use App\Models\Gateway;
use App\Services\ReliablePollingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FixPollingScheduleCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'polling:fix-schedule 
                            {--dry-run : Show what would be fixed without making changes}
                            {--force : Force restart polling for all active gateways}';

    /**
     * The console command description.
     */
    protected $description = 'Fix disconnect between gateway is_active flag and actual polling jobs';

    /**
     * Execute the console command.
     */
    public function handle(ReliablePollingService $pollingService): int
    {
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        $this->info('🔍 Analyzing polling schedule integrity...');
        
        // Get all gateways and their current polling status
        $gateways = Gateway::all();
        $issues = [];
        $fixed = [];
        
        foreach ($gateways as $gateway) {
            $statusKey = "gateway_polling_status_{$gateway->id}";
            $pollingStatus = Cache::get($statusKey);
            
            $issue = $this->analyzeGatewayPolling($gateway, $pollingStatus);
            
            if ($issue) {
                $issues[] = $issue;
                
                if (!$isDryRun) {
                    $fixResult = $this->fixGatewayPolling($gateway, $issue, $pollingService, $force);
                    if ($fixResult) {
                        $fixed[] = $fixResult;
                    }
                }
            }
        }
        
        // Display results
        $this->displayResults($issues, $fixed, $isDryRun);
        
        // Validate the fix worked
        if (!$isDryRun && !empty($fixed)) {
            $this->info('');
            $this->info('🔍 Validating fixes...');
            sleep(2); // Give jobs time to be scheduled
            
            $validationIssues = $pollingService->validatePollingIntegrity();
            
            if (empty($validationIssues)) {
                $this->info('✅ All polling schedule issues have been resolved');
                return 0;
            } else {
                $this->warn("⚠️  Some issues remain after fix attempt:");
                foreach ($validationIssues as $issue) {
                    $this->line("  - Gateway {$issue['gateway_id']}: {$issue['message']}");
                }
                return 1;
            }
        }
        
        return empty($issues) ? 0 : 1;
    }
    
    /**
     * Analyze a gateway's polling status and identify issues.
     */
    private function analyzeGatewayPolling(Gateway $gateway, ?array $pollingStatus): ?array
    {
        $issue = [
            'gateway_id' => $gateway->id,
            'gateway_name' => $gateway->name,
            'is_active' => $gateway->is_active,
            'poll_interval' => $gateway->poll_interval,
            'has_polling_status' => !is_null($pollingStatus),
            'polling_status' => $pollingStatus,
            'issue_type' => null,
            'description' => null,
            'action_needed' => null,
        ];
        
        if ($gateway->is_active) {
            if (!$pollingStatus) {
                // Active gateway with no polling scheduled
                $issue['issue_type'] = 'missing_polling';
                $issue['description'] = 'Active gateway has no polling jobs scheduled';
                $issue['action_needed'] = 'start_polling';
                return $issue;
            } else {
                // Check if polling is overdue
                $lastScheduled = \Carbon\Carbon::parse($pollingStatus['last_scheduled']);
                $expectedNext = $lastScheduled->addSeconds($gateway->poll_interval);
                
                if ($expectedNext->isPast()) {
                    $overdue = now()->diffInSeconds($expectedNext);
                    $issue['issue_type'] = 'overdue_polling';
                    $issue['description'] = "Polling is {$overdue} seconds overdue";
                    $issue['action_needed'] = 'restart_polling';
                    return $issue;
                }
                
                // Check if poll interval has changed
                if ($pollingStatus['poll_interval'] != $gateway->poll_interval) {
                    $issue['issue_type'] = 'interval_mismatch';
                    $issue['description'] = "Poll interval mismatch: scheduled={$pollingStatus['poll_interval']}s, current={$gateway->poll_interval}s";
                    $issue['action_needed'] = 'restart_polling';
                    return $issue;
                }
            }
        } else {
            if ($pollingStatus) {
                // Inactive gateway still has polling scheduled
                $issue['issue_type'] = 'unwanted_polling';
                $issue['description'] = 'Inactive gateway still has polling jobs scheduled';
                $issue['action_needed'] = 'stop_polling';
                return $issue;
            }
        }
        
        return null; // No issues found
    }
    
    /**
     * Fix a gateway's polling issue.
     */
    private function fixGatewayPolling(Gateway $gateway, array $issue, ReliablePollingService $pollingService, bool $force): ?array
    {
        $action = $issue['action_needed'];
        $result = [
            'gateway_id' => $gateway->id,
            'gateway_name' => $gateway->name,
            'action' => $action,
            'success' => false,
            'message' => '',
        ];
        
        try {
            switch ($action) {
                case 'start_polling':
                    if ($pollingService->startGatewayPolling($gateway)) {
                        $result['success'] = true;
                        $result['message'] = 'Started polling for active gateway';
                        Log::info("Fixed missing polling for gateway {$gateway->id}");
                    } else {
                        $result['message'] = 'Failed to start polling (may already be active)';
                    }
                    break;
                    
                case 'restart_polling':
                    // Stop current polling first
                    $pollingService->stopGatewayPolling($gateway);
                    
                    // Re-enable the gateway (in case it was disabled)
                    $gateway->update(['is_active' => true]);
                    
                    // Start fresh polling
                    if ($pollingService->startGatewayPolling($gateway)) {
                        $result['success'] = true;
                        $result['message'] = 'Restarted polling with correct interval';
                        Log::info("Fixed overdue/mismatched polling for gateway {$gateway->id}");
                    } else {
                        $result['message'] = 'Failed to restart polling';
                    }
                    break;
                    
                case 'stop_polling':
                    $pollingService->stopGatewayPolling($gateway);
                    $result['success'] = true;
                    $result['message'] = 'Stopped polling for inactive gateway';
                    Log::info("Stopped unwanted polling for gateway {$gateway->id}");
                    break;
                    
                default:
                    $result['message'] = "Unknown action: {$action}";
            }
            
        } catch (\Exception $e) {
            $result['message'] = "Error: " . $e->getMessage();
            Log::error("Failed to fix polling for gateway {$gateway->id}: " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Display analysis and fix results.
     */
    private function displayResults(array $issues, array $fixed, bool $isDryRun): void
    {
        if (empty($issues)) {
            $this->info('✅ No polling schedule issues found - all gateways are properly configured');
            return;
        }
        
        $this->warn("Found " . count($issues) . " polling schedule issues:");
        $this->line('');
        
        // Group issues by type
        $groupedIssues = collect($issues)->groupBy('issue_type');
        
        foreach ($groupedIssues as $type => $typeIssues) {
            $icon = $this->getIssueIcon($type);
            $this->line("{$icon} " . ucwords(str_replace('_', ' ', $type)) . " (" . count($typeIssues) . " gateways):");
            
            foreach ($typeIssues as $issue) {
                $this->line("  - Gateway {$issue['gateway_id']} ({$issue['gateway_name']}): {$issue['description']}");
            }
            $this->line('');
        }
        
        if ($isDryRun) {
            $this->info('💡 Run without --dry-run to fix these issues');
            $this->info('💡 Use --force to restart polling for all active gateways');
        } else {
            $this->line('');
            $this->info('🔧 Fix Results:');
            
            $successCount = collect($fixed)->where('success', true)->count();
            $failureCount = count($fixed) - $successCount;
            
            $this->table(
                ['Gateway ID', 'Name', 'Action', 'Result', 'Message'],
                collect($fixed)->map(function ($fix) {
                    return [
                        $fix['gateway_id'],
                        $fix['gateway_name'],
                        ucwords(str_replace('_', ' ', $fix['action'])),
                        $fix['success'] ? '✅ Success' : '❌ Failed',
                        $fix['message'],
                    ];
                })->toArray()
            );
            
            if ($successCount > 0) {
                $this->info("✅ Successfully fixed {$successCount} gateway(s)");
            }
            
            if ($failureCount > 0) {
                $this->warn("⚠️  Failed to fix {$failureCount} gateway(s)");
            }
        }
    }
    
    /**
     * Get icon for issue type.
     */
    private function getIssueIcon(string $type): string
    {
        return match ($type) {
            'missing_polling' => '❌',
            'overdue_polling' => '⏰',
            'interval_mismatch' => '🔄',
            'unwanted_polling' => '🛑',
            default => '⚠️',
        };
    }
}