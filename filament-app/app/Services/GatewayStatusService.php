<?php

namespace App\Services;

use App\Models\Gateway;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class GatewayStatusService
{
    const STATUS_ONLINE = 'online';
    const STATUS_DEGRADED = 'degraded';
    const STATUS_OFFLINE = 'offline';
    const STATUS_PAUSED = 'paused';

    const BADGE_COLORS = [
        self::STATUS_ONLINE => 'success',
        self::STATUS_DEGRADED => 'warning',
        self::STATUS_OFFLINE => 'danger',
        self::STATUS_PAUSED => 'gray',
    ];

    /**
     * Compute the enhanced status for a gateway with caching.
     */
    public function computeStatus($gateway): string
    {
        // Create cache key based on gateway ID and relevant attributes
        $cacheKey = $this->getStatusCacheKey($gateway);
        
        // Cache for the gateway's poll interval duration (minimum 30 seconds)
        $cacheDuration = max(30, $gateway->poll_interval);
        
        return Cache::remember($cacheKey, $cacheDuration, function () use ($gateway) {
            return $this->computeStatusUncached($gateway);
        });
    }

    /**
     * Compute the enhanced status for a gateway without caching.
     */
    private function computeStatusUncached($gateway): string
    {
        // If polling is disabled, return paused status
        if (!$gateway->is_active) {
            return self::STATUS_PAUSED;
        }

        // If no last_seen_at timestamp, consider offline
        if (!$gateway->last_seen_at) {
            return self::STATUS_OFFLINE;
        }

        $now = Carbon::now();
        $lastSeen = $gateway->last_seen_at;
        $pollInterval = $gateway->poll_interval;
        
        // Calculate time since last seen in seconds
        $timeSinceLastSeen = $now->diffInSeconds($lastSeen);
        
        // Define thresholds based on poll interval
        $onlineThreshold = $pollInterval * 2;
        $offlineThreshold = $pollInterval * 5;
        
        // Check if gateway is offline (>5× interval)
        if ($timeSinceLastSeen > $offlineThreshold) {
            return self::STATUS_OFFLINE;
        }
        
        // Check if gateway is online (<2× interval)
        if ($timeSinceLastSeen < $onlineThreshold) {
            // Also check error rate for degraded status
            $errorRate = $this->getRecentErrorRate($gateway);
            if ($errorRate > 20.0) {
                return self::STATUS_DEGRADED;
            }
            return self::STATUS_ONLINE;
        }
        
        // Between 2× and 5× interval, or high error rate = degraded
        return self::STATUS_DEGRADED;
    }

    /**
     * Get the badge color for a given status.
     */
    public function getStatusBadgeColor(string $status): string
    {
        return self::BADGE_COLORS[$status] ?? 'gray';
    }

    /**
     * Calculate recent error rate from last 20 polls using success/failure counts with caching.
     */
    public function getRecentErrorRate($gateway): float
    {
        $cacheKey = $this->getErrorRateCacheKey($gateway);
        
        // Cache error rate for half the poll interval (minimum 15 seconds)
        $cacheDuration = max(15, $gateway->poll_interval / 2);
        
        return Cache::remember($cacheKey, $cacheDuration, function () use ($gateway) {
            return $this->computeErrorRateUncached($gateway);
        });
    }

    /**
     * Calculate recent error rate without caching.
     */
    private function computeErrorRateUncached($gateway): float
    {
        $totalPolls = $gateway->success_count + $gateway->failure_count;
        
        // If no polls recorded, return 0% error rate
        if ($totalPolls === 0) {
            return 0.0;
        }
        
        // For now, calculate overall error rate as we don't have individual poll timestamps
        // In a real implementation, you'd query the last 20 poll records
        $errorRate = ($gateway->failure_count / $totalPolls) * 100;
        
        return round($errorRate, 2);
    }

    /**
     * Check if gateway's last seen time is within a threshold multiplier.
     */
    public function isWithinThreshold($gateway, int $multiplier): bool
    {
        if (!$gateway->last_seen_at) {
            return false;
        }

        $threshold = $gateway->poll_interval * $multiplier;
        $timeSinceLastSeen = Carbon::now()->diffInSeconds($gateway->last_seen_at);
        
        return $timeSinceLastSeen <= $threshold;
    }

    /**
     * Get all available status options.
     */
    public function getStatusOptions(): array
    {
        return [
            self::STATUS_ONLINE => 'Online',
            self::STATUS_DEGRADED => 'Degraded',
            self::STATUS_OFFLINE => 'Offline',
            self::STATUS_PAUSED => 'Paused',
        ];
    }

    /**
     * Get status with human-readable label.
     */
    public function getStatusLabel(string $status): string
    {
        $options = $this->getStatusOptions();
        return $options[$status] ?? 'Unknown';
    }

    /**
     * Generate cache key for gateway status.
     */
    private function getStatusCacheKey($gateway): string
    {
        // Include relevant attributes that affect status computation
        $keyData = [
            'gateway_status',
            $gateway->id,
            $gateway->is_active ? 1 : 0,
            $gateway->poll_interval,
            $gateway->last_seen_at?->timestamp ?? 0,
            $gateway->success_count,
            $gateway->failure_count,
        ];
        
        return 'gateway_status_' . md5(implode('_', $keyData));
    }

    /**
     * Generate cache key for gateway error rate.
     */
    private function getErrorRateCacheKey($gateway): string
    {
        $keyData = [
            'gateway_error_rate',
            $gateway->id,
            $gateway->success_count,
            $gateway->failure_count,
        ];
        
        return 'gateway_error_rate_' . md5(implode('_', $keyData));
    }

    /**
     * Invalidate cached status for a gateway.
     */
    public function invalidateGatewayCache($gateway): void
    {
        $statusKey = $this->getStatusCacheKey($gateway);
        $errorRateKey = $this->getErrorRateCacheKey($gateway);
        
        Cache::forget($statusKey);
        Cache::forget($errorRateKey);
    }

    /**
     * Invalidate all gateway status caches.
     */
    public function invalidateAllGatewayCaches(): void
    {
        // Use cache tags if available, otherwise we'd need to track keys
        // For now, we'll rely on TTL expiration
        Cache::flush(); // This is aggressive but ensures fresh data
    }

    /**
     * Compute statuses for multiple gateways efficiently.
     */
    public function computeMultipleStatuses($gateways): array
    {
        $statuses = [];
        
        foreach ($gateways as $gateway) {
            $statuses[$gateway->id] = $this->computeStatus($gateway);
        }
        
        return $statuses;
    }
}