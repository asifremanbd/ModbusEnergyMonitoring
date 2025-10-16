<?php

namespace App\Console\Commands;

use App\Models\Gateway;
use App\Services\GatewayStatsCacheService;
use Illuminate\Console\Command;

class WarmUpStatsCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warm-stats 
                            {--gateway=* : Specific gateway IDs to warm up}
                            {--clear : Clear cache before warming up}
                            {--chunk=50 : Number of gateways to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up the gateway statistics cache for improved performance';

    /**
     * Execute the console command.
     */
    public function handle(GatewayStatsCacheService $cacheService): int
    {
        $this->info('Starting gateway statistics cache warm-up...');
        
        // Clear cache if requested
        if ($this->option('clear')) {
            $this->info('Clearing existing cache...');
            $cacheService->clearAllCache();
        }
        
        // Get gateway IDs to process
        $gatewayIds = $this->option('gateway');
        if (empty($gatewayIds)) {
            $gatewayIds = Gateway::pluck('id')->toArray();
            $this->info('Processing all ' . count($gatewayIds) . ' gateways...');
        } else {
            $this->info('Processing ' . count($gatewayIds) . ' specified gateways...');
        }
        
        if (empty($gatewayIds)) {
            $this->warn('No gateways found to process.');
            return self::SUCCESS;
        }
        
        $chunkSize = (int) $this->option('chunk');
        $chunks = array_chunk($gatewayIds, $chunkSize);
        $totalProcessed = 0;
        
        $progressBar = $this->output->createProgressBar(count($gatewayIds));
        $progressBar->start();
        
        foreach ($chunks as $chunk) {
            try {
                // Warm up bulk cache for this chunk
                $cacheService->warmUpBulkCache($chunk);
                
                // Warm up individual gateway caches
                foreach ($chunk as $gatewayId) {
                    $cacheService->warmUpGatewayCache($gatewayId);
                    $progressBar->advance();
                    $totalProcessed++;
                }
                
                // Small delay to prevent overwhelming the database
                usleep(100000); // 0.1 seconds
                
            } catch (\Exception $e) {
                $this->error("Error processing gateway chunk: " . $e->getMessage());
                continue;
            }
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        // Display cache statistics
        $stats = $cacheService->getCacheStats();
        $this->info("Cache warm-up completed!");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Gateways Processed', $totalProcessed],
                ['Total Cache Keys', $stats['total_keys']],
                ['Cache TTL', $stats['cache_ttl'] . ' seconds'],
                ['Cache Prefix', $stats['cache_prefix']],
            ]
        );
        
        if (!empty($stats['sample_keys'])) {
            $this->info('Sample cache keys:');
            foreach (array_slice($stats['sample_keys'], 0, 5) as $key) {
                $this->line('  - ' . $key);
            }
        }
        
        return self::SUCCESS;
    }
}