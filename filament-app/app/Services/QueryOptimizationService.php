<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Service for optimizing database queries in the modular management system.
 */
class QueryOptimizationService
{
    /**
     * Optimize gateway queries with proper eager loading and indexing.
     */
    public function optimizeGatewayQuery(Builder $query): Builder
    {
        return $query
            ->select([
                'gateways.*',
                // Pre-calculate counts using subqueries for better performance
                DB::raw('(SELECT COUNT(*) FROM devices WHERE devices.gateway_id = gateways.id) as devices_count'),
                DB::raw('(SELECT COUNT(*) FROM devices WHERE devices.gateway_id = gateways.id AND devices.enabled = 1) as enabled_devices_count'),
                DB::raw('(SELECT COUNT(*) FROM registers INNER JOIN devices ON registers.device_id = devices.id WHERE devices.gateway_id = gateways.id) as registers_count'),
                DB::raw('(SELECT COUNT(*) FROM registers INNER JOIN devices ON registers.device_id = devices.id WHERE devices.gateway_id = gateways.id AND registers.enabled = 1) as enabled_registers_count'),
            ])
            ->with([
                'devices' => function ($query) {
                    $query->select(['id', 'gateway_id', 'device_name', 'device_type', 'enabled'])
                          ->withCount(['registers', 'registers as enabled_registers_count' => function ($q) {
                              $q->where('enabled', true);
                          }]);
                }
            ]);
    }

    /**
     * Optimize device queries with proper relationships and counts.
     */
    public function optimizeDeviceQuery(Builder $query): Builder
    {
        return $query
            ->select([
                'devices.*',
                // Pre-calculate register counts
                DB::raw('(SELECT COUNT(*) FROM registers WHERE registers.device_id = devices.id) as registers_count'),
                DB::raw('(SELECT COUNT(*) FROM registers WHERE registers.device_id = devices.id AND registers.enabled = 1) as enabled_registers_count'),
            ])
            ->with([
                'gateway:id,name,ip_address,port',
                'registers' => function ($query) {
                    $query->select(['id', 'device_id', 'technical_label', 'enabled', 'function', 'register_address']);
                }
            ]);
    }

    /**
     * Optimize register queries with device and gateway information.
     */
    public function optimizeRegisterQuery(Builder $query): Builder
    {
        return $query
            ->with([
                'device' => function ($query) {
                    $query->select(['id', 'gateway_id', 'device_name', 'device_type'])
                          ->with('gateway:id,name,ip_address,port');
                }
            ]);
    }

    /**
     * Create optimized query for gateway statistics.
     */
    public function getGatewayStatsQuery(array $gatewayIds = []): Builder
    {
        $query = DB::table('gateways as g')
            ->leftJoin('devices as d', 'g.id', '=', 'd.gateway_id')
            ->leftJoin('registers as r', 'd.id', '=', 'r.device_id')
            ->select([
                'g.id',
                'g.name',
                'g.ip_address',
                'g.port',
                'g.is_active',
                'g.last_seen_at',
                DB::raw('COUNT(DISTINCT d.id) as device_count'),
                DB::raw('COUNT(DISTINCT CASE WHEN d.enabled = 1 THEN d.id END) as enabled_device_count'),
                DB::raw('COUNT(r.id) as register_count'),
                DB::raw('COUNT(CASE WHEN r.enabled = 1 THEN r.id END) as enabled_register_count'),
            ])
            ->groupBy(['g.id', 'g.name', 'g.ip_address', 'g.port', 'g.is_active', 'g.last_seen_at']);

        if (!empty($gatewayIds)) {
            $query->whereIn('g.id', $gatewayIds);
        }

        return $query;
    }

    /**
     * Create optimized query for device statistics.
     */
    public function getDeviceStatsQuery(array $deviceIds = []): Builder
    {
        $query = DB::table('devices as d')
            ->leftJoin('registers as r', 'd.id', '=', 'r.device_id')
            ->join('gateways as g', 'd.gateway_id', '=', 'g.id')
            ->select([
                'd.id',
                'd.device_name',
                'd.device_type',
                'd.load_category',
                'd.enabled',
                'g.name as gateway_name',
                'g.ip_address as gateway_ip',
                DB::raw('COUNT(r.id) as register_count'),
                DB::raw('COUNT(CASE WHEN r.enabled = 1 THEN r.id END) as enabled_register_count'),
                DB::raw('COUNT(CASE WHEN r.enabled = 0 THEN r.id END) as disabled_register_count'),
            ])
            ->groupBy(['d.id', 'd.device_name', 'd.device_type', 'd.load_category', 'd.enabled', 'g.name', 'g.ip_address']);

        if (!empty($deviceIds)) {
            $query->whereIn('d.id', $deviceIds);
        }

        return $query;
    }

    /**
     * Optimize queries for FilamentPHP table performance.
     */
    public function optimizeFilamentTableQuery(Builder $query, array $options = []): Builder
    {
        // Apply select optimization to reduce memory usage
        if (isset($options['select']) && !empty($options['select'])) {
            $query->select($options['select']);
        }

        // Apply eager loading optimization
        if (isset($options['with']) && !empty($options['with'])) {
            $query->with($options['with']);
        }

        // Apply index hints for large tables
        if (isset($options['use_index']) && !empty($options['use_index'])) {
            $tableName = $query->getModel()->getTable();
            $query->from(DB::raw("{$tableName} USE INDEX ({$options['use_index']})"));
        }

        // Apply query caching for expensive operations
        if (isset($options['cache_minutes']) && $options['cache_minutes'] > 0) {
            $query->remember($options['cache_minutes']);
        }

        return $query;
    }

    /**
     * Create efficient count query that avoids full table scans.
     */
    public function getEfficientCount(Builder $query): int
    {
        // Clone query to avoid modifying original
        $countQuery = clone $query;
        
        // Remove unnecessary clauses for counting
        $countQuery->getQuery()->orders = null;
        $countQuery->getQuery()->limit = null;
        $countQuery->getQuery()->offset = null;
        
        // Use approximate counting for very large result sets
        $tableName = $countQuery->getModel()->getTable();
        
        try {
            // First try exact count with timeout
            DB::statement('SET SESSION max_execution_time = 5'); // 5 second timeout
            $count = $countQuery->count();
            DB::statement('SET SESSION max_execution_time = 0'); // Reset timeout
            
            return $count;
        } catch (\Exception $e) {
            // Fallback to approximate count
            return $this->getApproximateCount($countQuery);
        }
    }

    /**
     * Get approximate count using EXPLAIN for complex queries.
     */
    private function getApproximateCount(Builder $query): int
    {
        try {
            $sql = $query->toSql();
            $bindings = $query->getBindings();
            
            // Use EXPLAIN to estimate rows
            $result = DB::select("EXPLAIN " . $sql, $bindings);
            
            $estimatedRows = 0;
            foreach ($result as $row) {
                $estimatedRows += (int) ($row->rows ?? 0);
            }
            
            return max($estimatedRows, 1);
        } catch (\Exception $e) {
            // Ultimate fallback
            return 1000; // Reasonable default for pagination
        }
    }

    /**
     * Optimize bulk operations for better performance.
     */
    public function optimizeBulkOperation(string $table, array $data, string $operation = 'insert'): bool
    {
        try {
            DB::beginTransaction();
            
            switch ($operation) {
                case 'insert':
                    // Use batch insert for better performance
                    DB::table($table)->insert($data);
                    break;
                    
                case 'update':
                    // Use batch update with CASE statements
                    $this->performBatchUpdate($table, $data);
                    break;
                    
                case 'upsert':
                    // Use INSERT ... ON DUPLICATE KEY UPDATE
                    $this->performBatchUpsert($table, $data);
                    break;
            }
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Perform batch update using CASE statements.
     */
    private function performBatchUpdate(string $table, array $data): void
    {
        if (empty($data)) {
            return;
        }
        
        $ids = array_column($data, 'id');
        $updateFields = array_keys($data[0]);
        $updateFields = array_filter($updateFields, fn($field) => $field !== 'id');
        
        $caseClauses = [];
        foreach ($updateFields as $field) {
            $cases = [];
            foreach ($data as $row) {
                $id = $row['id'];
                $value = $row[$field] ?? null;
                $cases[] = "WHEN {$id} THEN " . DB::getPdo()->quote($value);
            }
            $caseClauses[] = "{$field} = CASE id " . implode(' ', $cases) . " END";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $caseClauses) . 
               " WHERE id IN (" . implode(',', $ids) . ")";
        
        DB::statement($sql);
    }

    /**
     * Perform batch upsert operation.
     */
    private function performBatchUpsert(string $table, array $data): void
    {
        if (empty($data)) {
            return;
        }
        
        $columns = array_keys($data[0]);
        $values = [];
        
        foreach ($data as $row) {
            $rowValues = [];
            foreach ($columns as $column) {
                $rowValues[] = DB::getPdo()->quote($row[$column] ?? null);
            }
            $values[] = '(' . implode(',', $rowValues) . ')';
        }
        
        $updateClauses = [];
        foreach ($columns as $column) {
            if ($column !== 'id') {
                $updateClauses[] = "{$column} = VALUES({$column})";
            }
        }
        
        $sql = "INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES " . 
               implode(',', $values) . " ON DUPLICATE KEY UPDATE " . 
               implode(',', $updateClauses);
        
        DB::statement($sql);
    }

    /**
     * Get query performance statistics.
     */
    public function getQueryStats(Builder $query): array
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        
        try {
            // Get query execution plan
            $explain = DB::select("EXPLAIN " . $sql, $bindings);
            
            // Calculate estimated cost
            $estimatedRows = 0;
            $usesIndex = false;
            
            foreach ($explain as $row) {
                $estimatedRows += (int) ($row->rows ?? 0);
                if (!empty($row->key)) {
                    $usesIndex = true;
                }
            }
            
            return [
                'sql' => $sql,
                'estimated_rows' => $estimatedRows,
                'uses_index' => $usesIndex,
                'explain' => $explain,
            ];
        } catch (\Exception $e) {
            return [
                'sql' => $sql,
                'error' => $e->getMessage(),
            ];
        }
    }
}