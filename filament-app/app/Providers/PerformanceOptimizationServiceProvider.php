<?php

namespace App\Providers;

use App\Services\GatewayStatsCacheService;
use App\Services\QueryOptimizationService;
use App\Services\OptimizedPaginationService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PerformanceOptimizationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register optimization services as singletons
        $this->app->singleton(GatewayStatsCacheService::class);
        $this->app->singleton(QueryOptimizationService::class);
        $this->app->singleton(OptimizedPaginationService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Enable query logging in development
        if (config('app.debug')) {
            DB::enableQueryLog();
        }
        
        // Add global query optimizations
        $this->addGlobalQueryOptimizations();
        
        // Register query macros for optimization
        $this->registerQueryMacros();
        
        // Set up cache warming schedule
        $this->setupCacheWarming();
    }
    
    /**
     * Add global query optimizations.
     */
    private function addGlobalQueryOptimizations(): void
    {
        // Optimize default pagination size for large datasets
        Builder::macro('optimizedPaginate', function (int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null) {
            return $this->app[OptimizedPaginationService::class]->paginate($this, $perPage, $page, [
                'columns' => $columns,
                'pageName' => $pageName,
            ]);
        });
        
        // Add cursor pagination for very large datasets
        Builder::macro('cursorPaginate', function (int $perPage = 15, ?string $cursor = null, array $options = []) {
            return $this->app[OptimizedPaginationService::class]->cursorPaginate($this, $perPage, $cursor, $options);
        });
    }
    
    /**
     * Register query optimization macros.
     */
    private function registerQueryMacros(): void
    {
        // Macro for efficient counting
        Builder::macro('efficientCount', function () {
            return $this->app[QueryOptimizationService::class]->getEfficientCount($this);
        });
        
        // Macro for optimized FilamentPHP table queries
        Builder::macro('optimizeForFilament', function (array $options = []) {
            return $this->app[QueryOptimizationService::class]->optimizeFilamentTableQuery($this, $options);
        });
        
        // Macro for gateway statistics optimization
        Builder::macro('withGatewayStats', function () {
            return $this->app[QueryOptimizationService::class]->optimizeGatewayQuery($this);
        });
        
        // Macro for device statistics optimization
        Builder::macro('withDeviceStats', function () {
            return $this->app[QueryOptimizationService::class]->optimizeDeviceQuery($this);
        });
        
        // Macro for register optimization
        Builder::macro('withRegisterOptimization', function () {
            return $this->app[QueryOptimizationService::class]->optimizeRegisterQuery($this);
        });
    }
    
    /**
     * Set up cache warming schedule.
     */
    private function setupCacheWarming(): void
    {
        // This would typically be done in the console kernel,
        // but we can set up the service here for dependency injection
        $this->app->resolving('command.schedule.run', function ($scheduler) {
            // Warm up cache every 5 minutes during business hours
            $scheduler->command('cache:warm-stats')
                ->everyFiveMinutes()
                ->between('08:00', '18:00')
                ->withoutOverlapping()
                ->runInBackground();
                
            // Full cache refresh once per hour
            $scheduler->command('cache:warm-stats --clear')
                ->hourly()
                ->withoutOverlapping()
                ->runInBackground();
        });
    }
}