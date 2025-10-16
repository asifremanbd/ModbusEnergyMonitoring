<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OptimizeFilamentQueries
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply optimizations to Filament admin routes
        if (!$request->is('admin/*')) {
            return $next($request);
        }
        
        // Set MySQL query optimizations for large datasets
        $this->setDatabaseOptimizations();
        
        // Enable query result caching for read operations
        if ($request->isMethod('GET')) {
            $this->enableQueryCaching();
        }
        
        $response = $next($request);
        
        // Log slow queries in development
        if (config('app.debug')) {
            $this->logSlowQueries();
        }
        
        return $response;
    }
    
    /**
     * Set database optimizations for better performance.
     */
    private function setDatabaseOptimizations(): void
    {
        try {
            // Optimize MySQL settings for large result sets
            DB::statement('SET SESSION query_cache_type = ON');
            DB::statement('SET SESSION query_cache_size = 67108864'); // 64MB
            
            // Optimize for read-heavy workloads
            DB::statement('SET SESSION read_buffer_size = 2097152'); // 2MB
            DB::statement('SET SESSION sort_buffer_size = 4194304'); // 4MB
            
            // Set reasonable timeouts
            DB::statement('SET SESSION max_execution_time = 30'); // 30 seconds
            
        } catch (\Exception $e) {
            // Ignore errors if MySQL doesn't support these settings
        }
    }
    
    /**
     * Enable query result caching for read operations.
     */
    private function enableQueryCaching(): void
    {
        try {
            // Enable query result caching
            DB::statement('SET SESSION query_cache_type = ON');
            
        } catch (\Exception $e) {
            // Ignore errors if caching is not available
        }
    }
    
    /**
     * Log slow queries for performance monitoring.
     */
    private function logSlowQueries(): void
    {
        $queries = DB::getQueryLog();
        
        foreach ($queries as $query) {
            // Log queries that take longer than 1 second
            if ($query['time'] > 1000) {
                \Log::warning('Slow query detected', [
                    'sql' => $query['query'],
                    'bindings' => $query['bindings'],
                    'time' => $query['time'] . 'ms',
                    'url' => request()->url(),
                ]);
            }
        }
    }
}