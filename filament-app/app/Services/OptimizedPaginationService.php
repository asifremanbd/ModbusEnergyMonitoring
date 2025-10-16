<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Service for optimized pagination with efficient counting for large datasets.
 */
class OptimizedPaginationService
{
    /**
     * Create an optimized paginator that uses efficient counting for large datasets.
     */
    public function paginate(Builder $query, int $perPage = 15, ?int $page = null, array $options = []): LengthAwarePaginator
    {
        $page = $page ?: request()->get('page', 1);
        
        // For large datasets, use approximate counting to improve performance
        $total = $this->getOptimizedCount($query);
        
        // Get the items for the current page
        $items = $query->forPage($page, $perPage)->get();
        
        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            array_merge($options, [
                'path' => request()->url(),
                'pageName' => 'page',
            ])
        );
    }

    /**
     * Get optimized count for large datasets.
     */
    private function getOptimizedCount(Builder $query): int
    {
        // Clone the query to avoid modifying the original
        $countQuery = clone $query;
        
        // Remove unnecessary clauses for counting
        $countQuery->getQuery()->orders = null;
        $countQuery->getQuery()->limit = null;
        $countQuery->getQuery()->offset = null;
        
        // For very large tables, use approximate counting
        $tableName = $countQuery->getModel()->getTable();
        $approximateCount = $this->getApproximateTableCount($tableName);
        
        // If the table is large (>10k rows), use approximate counting for performance
        if ($approximateCount > 10000) {
            // Use EXPLAIN to get approximate count for complex queries
            return $this->getApproximateQueryCount($countQuery);
        }
        
        // For smaller tables, use exact counting
        return $countQuery->count();
    }

    /**
     * Get approximate table count from information schema.
     */
    private function getApproximateTableCount(string $tableName): int
    {
        try {
            $result = DB::select("
                SELECT table_rows 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = ?
            ", [$tableName]);
            
            return (int) ($result[0]->table_rows ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get approximate count for complex queries using EXPLAIN.
     */
    private function getApproximateQueryCount(Builder $query): int
    {
        try {
            // Convert the query to SQL
            $sql = $query->toSql();
            $bindings = $query->getBindings();
            
            // Use EXPLAIN to get row estimate
            $explainSql = "EXPLAIN " . $sql;
            $result = DB::select($explainSql, $bindings);
            
            // Sum up the estimated rows from all tables in the query
            $estimatedRows = 0;
            foreach ($result as $row) {
                $estimatedRows += (int) ($row->rows ?? 0);
            }
            
            return max($estimatedRows, 1); // Ensure at least 1 for pagination
        } catch (\Exception $e) {
            // Fallback to exact count if EXPLAIN fails
            return $query->count();
        }
    }

    /**
     * Create a cursor-based paginator for very large datasets.
     */
    public function cursorPaginate(Builder $query, int $perPage = 15, ?string $cursor = null, array $options = []): array
    {
        $cursorColumn = $options['cursor_column'] ?? 'id';
        $direction = $options['direction'] ?? 'asc';
        
        // Apply cursor condition if provided
        if ($cursor) {
            $operator = $direction === 'asc' ? '>' : '<';
            $query->where($cursorColumn, $operator, $cursor);
        }
        
        // Get one extra item to determine if there are more pages
        $items = $query->orderBy($cursorColumn, $direction)
                      ->limit($perPage + 1)
                      ->get();
        
        $hasMore = $items->count() > $perPage;
        
        // Remove the extra item if it exists
        if ($hasMore) {
            $items->pop();
        }
        
        // Get the next cursor
        $nextCursor = $hasMore && $items->isNotEmpty() 
            ? $items->last()->{$cursorColumn}
            : null;
        
        return [
            'data' => $items,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
            'per_page' => $perPage,
        ];
    }

    /**
     * Optimize query for large dataset pagination.
     */
    public function optimizeQueryForPagination(Builder $query): Builder
    {
        // Add indexes hints for common pagination patterns
        $model = $query->getModel();
        $tableName = $model->getTable();
        
        // Use index hints for better performance on large tables
        if ($this->getApproximateTableCount($tableName) > 50000) {
            // Force use of primary key index for ordering
            $query->getQuery()->from = DB::raw("{$tableName} USE INDEX (PRIMARY)");
        }
        
        return $query;
    }

    /**
     * Get pagination metadata for API responses.
     */
    public function getPaginationMetadata(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more_pages' => $paginator->hasMorePages(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];
    }

    /**
     * Create efficient pagination for FilamentPHP tables.
     */
    public function createFilamentPagination(Builder $query, array $options = []): Builder
    {
        // Apply optimizations for large datasets
        $query = $this->optimizeQueryForPagination($query);
        
        // Add efficient eager loading
        if (isset($options['with'])) {
            $query->with($options['with']);
        }
        
        // Add select optimization to reduce memory usage
        if (isset($options['select'])) {
            $query->select($options['select']);
        }
        
        return $query;
    }
}