<?php

namespace App\Services;

use App\Models\Gateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service for caching frequently accessed gateway statistics to improve performance.
 */
class GatewayStatsCacheService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const CACHE_PREFIX = 'gateway_stats_';

    /**
     * Get cached device count for a gateway.
     */
    public function getDeviceCount(int $gatewayId): int
    {
        $cacheKey = self::CACHE_PREFIX . "device_count_{$gatewayId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($gatewayId) {
            return DB::table('devices')
                ->where('gateway_id', $gatewayId)
                ->count();
        });
    }

    /**
     * Get cached register count for a gateway.
     */
    public function getRegisterCount(int $gatewayId): int
    {
        $cacheKey = self::CACHE_PREFIX . "register_count_{$gatewayId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($gatewayId) {
            return DB::table('registers')
                ->join('devices', 'registers.device_id', '=', 'devices.id')
                ->where('devices.gateway_id', $gatewayId)
                ->count();
        });
    }

    /**
     * Get cached enabled device count for a gateway.
     */
    public function getEnabledDeviceCount(int $gatewayId): int
    {
        $cacheKey = self::CACHE_PREFIX . "enabled_device_count_{$gatewayId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($gatewayId) {
            return DB::table('devices')
                ->where('gateway_id', $gatewayId)
                ->where('enabled', true)
                ->count();
        });
    }

    /**
     * Get cached enabled register count for a gateway.
     */
    public function getEnabledRegisterCount(int $gatewayId): int
    {
        $cacheKey = self::CACHE_PREFIX . "enabled_register_count_{$gatewayId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($gatewayId) {
            return DB::table('registers')
                ->join('devices', 'registers.device_id', '=', 'devices.id')
                ->where('devices.gateway_id', $gatewayId)
                ->where('registers.enabled', true)
                ->count();
        });
    }

    /**
     * Get cached gateway statistics summary.
     */
    public function getGatewayStats(int $gatewayId): array
    {
        $cacheKey = self::CACHE_PREFIX . "summary_{$gatewayId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($gatewayId) {
            // Use optimized single query to get all stats
            $stats = DB::select("
                SELECT 
                    COUNT(DISTINCT d.id) as device_count,
                    COUNT(DISTINCT CASE WHEN d.enabled = 1 THEN d.id END) as enabled_device_count,
                    COUNT(r.id) as register_count,
                    COUNT(CASE WHEN r.enabled = 1 THEN r.id END) as enabled_register_count
                FROM devices d
                LEFT JOIN registers r ON d.id = r.device_id
                WHERE d.gateway_id = ?
            ", [$gatewayId]);

            $result = $stats[0] ?? null;
            
            return [
                'device_count' => (int) ($result->device_count ?? 0),
                'enabled_device_count' => (int) ($result->enabled_device_count ?? 0),
                'register_count' => (int) ($result->register_count ?? 0),
                'enabled_register_count' => (int) ($result->enabled_register_count ?? 0),
            ];
        });
    }

    /**
     * Get cached statistics for multiple gateways.
     */
    public function getBulkGatewayStats(array $gatewayIds): array
    {
        sort($gatewayIds);
        $cacheKey = self::CACHE_PREFIX . "bulk_" . md5(implode(',', $gatewayIds));
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($gatewayIds) {
            if (empty($gatewayIds)) {
                return [];
            }

            $placeholders = str_repeat('?,', count($gatewayIds) - 1) . '?';
            
            $stats = DB::select("
                SELECT 
                    d.gateway_id,
                    COUNT(DISTINCT d.id) as device_count,
                    COUNT(DISTINCT CASE WHEN d.enabled = 1 THEN d.id END) as enabled_device_count,
                    COUNT(r.id) as register_count,
                    COUNT(CASE WHEN r.enabled = 1 THEN r.id END) as enabled_register_count
                FROM devices d
                LEFT JOIN registers r ON d.id = r.device_id
                WHERE d.gateway_id IN ({$placeholders})
                GROUP BY d.gateway_id
            ", $gatewayIds);

            $result = [];
            foreach ($stats as $stat) {
                $result[$stat->gateway_id] = [
                    'device_count' => (int) $stat->device_count,
                    'enabled_device_count' => (int) $stat->enabled_device_count,
                    'register_count' => (int) $stat->register_count,
                    'enabled_register_count' => (int) $stat->enabled_register_count,
                ];
            }

            // Fill in missing gateways with zero counts
            foreach ($gatewayIds as $gatewayId) {
                if (!isset($result[$gatewayId])) {
                    $result[$gatewayId] = [
                        'device_count' => 0,
                        'enabled_device_count' => 0,
                        'register_count' => 0,
                        'enabled_register_count' => 0,
                    ];
                }
            }

            return $result;
        });
    }

    /**
     * Get cached device statistics for a specific device.
     */
    public function getDeviceStats(int $deviceId): array
    {
        $cacheKey = self::CACHE_PREFIX . "device_{$deviceId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($deviceId) {
            $stats = DB::select("
                SELECT 
                    COUNT(r.id) as register_count,
                    COUNT(CASE WHEN r.enabled = 1 THEN r.id END) as enabled_register_count,
                    COUNT(CASE WHEN r.enabled = 0 THEN r.id END) as disabled_register_count
                FROM registers r
                WHERE r.device_id = ?
            ", [$deviceId]);

            $result = $stats[0] ?? null;
            
            return [
                'register_count' => (int) ($result->register_count ?? 0),
                'enabled_register_count' => (int) ($result->enabled_register_count ?? 0),
                'disabled_register_count' => (int) ($result->disabled_register_count ?? 0),
            ];
        });
    }

    /**
     * Clear cache for a specific gateway.
     */
    public function clearGatewayCache(int $gatewayId): void
    {
        $keys = [
            self::CACHE_PREFIX . "device_count_{$gatewayId}",
            self::CACHE_PREFIX . "register_count_{$gatewayId}",
            self::CACHE_PREFIX . "enabled_device_count_{$gatewayId}",
            self::CACHE_PREFIX . "enabled_register_count_{$gatewayId}",
            self::CACHE_PREFIX . "summary_{$gatewayId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // Clear bulk cache keys that might contain this gateway
        $this->clearBulkCache();
    }

    /**
     * Clear cache for a specific device.
     */
    public function clearDeviceCache(int $deviceId): void
    {
        Cache::forget(self::CACHE_PREFIX . "device_{$deviceId}");
        
        // Also clear gateway cache since device stats affect gateway stats
        $device = DB::table('devices')->where('id', $deviceId)->first();
        if ($device) {
            $this->clearGatewayCache($device->gateway_id);
        }
    }

    /**
     * Clear all bulk cache entries.
     */
    public function clearBulkCache(): void
    {
        // For file cache, we'll use a different approach
        if (config('cache.default') === 'redis') {
            $cacheKeys = Cache::getRedis()->keys(self::CACHE_PREFIX . "bulk_*");
            foreach ($cacheKeys as $key) {
                Cache::forget(str_replace(config('cache.prefix') . ':', '', $key));
            }
        } else {
            // For file cache, we'll track bulk keys manually or use cache tags
            // For now, we'll just clear the entire cache store
            Cache::flush();
        }
    }

    /**
     * Clear all gateway statistics cache.
     */
    public function clearAllCache(): void
    {
        if (config('cache.default') === 'redis') {
            $cacheKeys = Cache::getRedis()->keys(self::CACHE_PREFIX . "*");
            foreach ($cacheKeys as $key) {
                Cache::forget(str_replace(config('cache.prefix') . ':', '', $key));
            }
        } else {
            // For file cache, flush all cache
            Cache::flush();
        }
    }

    /**
     * Warm up cache for a gateway.
     */
    public function warmUpGatewayCache(int $gatewayId): void
    {
        // Pre-load all statistics for the gateway
        $this->getGatewayStats($gatewayId);
        
        // Pre-load device statistics for all devices in the gateway
        $deviceIds = DB::table('devices')
            ->where('gateway_id', $gatewayId)
            ->pluck('id');
            
        foreach ($deviceIds as $deviceId) {
            $this->getDeviceStats($deviceId);
        }
    }

    /**
     * Warm up cache for multiple gateways.
     */
    public function warmUpBulkCache(array $gatewayIds): void
    {
        if (empty($gatewayIds)) {
            return;
        }

        // Pre-load bulk statistics
        $this->getBulkGatewayStats($gatewayIds);
        
        // Pre-load individual gateway statistics
        foreach ($gatewayIds as $gatewayId) {
            $this->warmUpGatewayCache($gatewayId);
        }
    }

    /**
     * Get cache statistics for monitoring.
     */
    public function getCacheStats(): array
    {
        if (config('cache.default') === 'redis') {
            $cacheKeys = Cache::getRedis()->keys(self::CACHE_PREFIX . "*");
            return [
                'total_keys' => count($cacheKeys),
                'cache_prefix' => self::CACHE_PREFIX,
                'cache_ttl' => self::CACHE_TTL,
                'sample_keys' => array_slice($cacheKeys, 0, 10),
            ];
        } else {
            // For file cache, we can't easily enumerate keys
            return [
                'total_keys' => 'N/A (file cache)',
                'cache_prefix' => self::CACHE_PREFIX,
                'cache_ttl' => self::CACHE_TTL,
                'sample_keys' => [],
            ];
        }
    }
}