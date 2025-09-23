<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PollingOptimizationService
{
    private const ACTIVE_USERS_KEY = 'active_polling_users';
    private const USER_ACTIVITY_PREFIX = 'user_activity_';
    private const MAX_CONCURRENT_POLLS = 50; // Limit concurrent polling requests
    
    /**
     * Register a user as actively polling.
     */
    public function registerActiveUser(string $sessionId, int $refreshInterval): void
    {
        $userKey = self::USER_ACTIVITY_PREFIX . $sessionId;
        $userData = [
            'session_id' => $sessionId,
            'refresh_interval' => $refreshInterval,
            'last_activity' => now()->timestamp,
        ];
        
        // Store user activity with TTL slightly longer than refresh interval
        $ttl = max(60, $refreshInterval * 2);
        Cache::put($userKey, $userData, $ttl);
        
        // Update active users list
        $this->updateActiveUsersList($sessionId, $userData);
    }

    /**
     * Check if polling should be throttled based on server load.
     */
    public function shouldThrottlePolling(string $sessionId): bool
    {
        $activeUsers = $this->getActiveUsersCount();
        
        // If too many concurrent users, throttle new requests
        if ($activeUsers > self::MAX_CONCURRENT_POLLS) {
            Log::warning("Polling throttled: {$activeUsers} active users exceed limit");
            return true;
        }
        
        // Check if this specific user is polling too frequently
        return $this->isUserPollingTooFrequently($sessionId);
    }

    /**
     * Get the number of currently active polling users.
     */
    public function getActiveUsersCount(): int
    {
        $activeUsers = Cache::get(self::ACTIVE_USERS_KEY, []);
        
        // Clean up expired users
        $now = now()->timestamp;
        $activeUsers = array_filter($activeUsers, function ($userData) use ($now) {
            return ($now - $userData['last_activity']) < 120; // 2 minutes timeout
        });
        
        // Update the cleaned list
        Cache::put(self::ACTIVE_USERS_KEY, $activeUsers, 300);
        
        return count($activeUsers);
    }

    /**
     * Get optimal refresh interval based on server load.
     */
    public function getOptimalRefreshInterval(int $requestedInterval): int
    {
        $activeUsers = $this->getActiveUsersCount();
        
        // If server is under heavy load, suggest longer intervals
        if ($activeUsers > 30) {
            return max($requestedInterval, 10); // Minimum 10 seconds under load
        } elseif ($activeUsers > 20) {
            return max($requestedInterval, 5); // Minimum 5 seconds under moderate load
        }
        
        return $requestedInterval; // No adjustment needed
    }

    /**
     * Clean up inactive users from tracking.
     */
    public function cleanupInactiveUsers(): void
    {
        $activeUsers = Cache::get(self::ACTIVE_USERS_KEY, []);
        $now = now()->timestamp;
        
        $cleanedUsers = array_filter($activeUsers, function ($userData) use ($now) {
            return ($now - $userData['last_activity']) < 300; // 5 minutes timeout
        });
        
        Cache::put(self::ACTIVE_USERS_KEY, $cleanedUsers, 300);
        
        // Also clean up individual user activity records
        foreach ($activeUsers as $sessionId => $userData) {
            if (($now - $userData['last_activity']) >= 300) {
                Cache::forget(self::USER_ACTIVITY_PREFIX . $sessionId);
            }
        }
    }

    /**
     * Get polling statistics for monitoring.
     */
    public function getPollingStats(): array
    {
        $activeUsers = Cache::get(self::ACTIVE_USERS_KEY, []);
        $intervalCounts = [];
        
        foreach ($activeUsers as $userData) {
            $interval = $userData['refresh_interval'];
            $intervalCounts[$interval] = ($intervalCounts[$interval] ?? 0) + 1;
        }
        
        return [
            'active_users' => count($activeUsers),
            'interval_distribution' => $intervalCounts,
            'server_load_level' => $this->getServerLoadLevel(),
        ];
    }

    /**
     * Update the active users list.
     */
    private function updateActiveUsersList(string $sessionId, array $userData): void
    {
        $activeUsers = Cache::get(self::ACTIVE_USERS_KEY, []);
        $activeUsers[$sessionId] = $userData;
        
        Cache::put(self::ACTIVE_USERS_KEY, $activeUsers, 300);
    }

    /**
     * Check if a user is polling too frequently.
     */
    private function isUserPollingTooFrequently(string $sessionId): bool
    {
        $userKey = self::USER_ACTIVITY_PREFIX . $sessionId;
        $userData = Cache::get($userKey);
        
        if (!$userData) {
            return false;
        }
        
        $timeSinceLastActivity = now()->timestamp - $userData['last_activity'];
        $expectedInterval = $userData['refresh_interval'];
        
        // Allow some tolerance (80% of expected interval)
        $minInterval = $expectedInterval * 0.8;
        
        return $timeSinceLastActivity < $minInterval;
    }

    /**
     * Get current server load level.
     */
    private function getServerLoadLevel(): string
    {
        $activeUsers = $this->getActiveUsersCount();
        
        if ($activeUsers > 40) {
            return 'high';
        } elseif ($activeUsers > 20) {
            return 'moderate';
        } else {
            return 'low';
        }
    }
}