<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

class WebSocketService
{
    private bool $isConnected = false;
    private array $connectionAttempts = [];
    private int $maxRetries = 3;
    private int $retryDelay = 5; // seconds

    /**
     * Check if WebSocket connection is available
     */
    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    /**
     * Set connection status
     */
    public function setConnectionStatus(bool $connected): void
    {
        $this->isConnected = $connected;
        
        if ($connected) {
            Log::info('WebSocket connection established');
            $this->resetConnectionAttempts();
        } else {
            Log::warning('WebSocket connection lost');
            $this->recordConnectionAttempt();
        }
    }

    /**
     * Check if fallback polling should be used
     */
    public function shouldUseFallback(): bool
    {
        return !$this->isConnected || $this->hasExceededRetryLimit();
    }

    /**
     * Get recommended fallback polling interval
     */
    public function getFallbackInterval(): int
    {
        // More frequent polling if recently disconnected, less frequent if persistently down
        $attemptCount = count($this->connectionAttempts);
        
        if ($attemptCount === 0) {
            return 5; // 5 seconds for first fallback
        } elseif ($attemptCount <= 3) {
            return 10; // 10 seconds for early attempts
        } else {
            return 30; // 30 seconds for persistent issues
        }
    }

    /**
     * Record a connection attempt
     */
    private function recordConnectionAttempt(): void
    {
        $this->connectionAttempts[] = now();
        
        // Keep only recent attempts (last hour)
        $this->connectionAttempts = array_filter(
            $this->connectionAttempts,
            fn($attempt) => $attempt->gt(now()->subHour())
        );
    }

    /**
     * Reset connection attempts counter
     */
    private function resetConnectionAttempts(): void
    {
        $this->connectionAttempts = [];
    }

    /**
     * Check if retry limit has been exceeded
     */
    private function hasExceededRetryLimit(): bool
    {
        $recentAttempts = array_filter(
            $this->connectionAttempts,
            fn($attempt) => $attempt->gt(now()->subMinutes(5))
        );
        
        return count($recentAttempts) >= $this->maxRetries;
    }

    /**
     * Get connection health status
     */
    public function getHealthStatus(): array
    {
        return [
            'connected' => $this->isConnected,
            'should_use_fallback' => $this->shouldUseFallback(),
            'fallback_interval' => $this->getFallbackInterval(),
            'recent_attempts' => count($this->connectionAttempts),
            'last_attempt' => !empty($this->connectionAttempts) 
                ? end($this->connectionAttempts)->toISOString() 
                : null,
        ];
    }

    /**
     * Test WebSocket connectivity
     */
    public function testConnection(): bool
    {
        try {
            // In a real implementation, this would test the actual WebSocket connection
            // For now, we'll simulate based on configuration
            $driver = config('broadcasting.default');
            $pusherConfig = config('broadcasting.connections.pusher');
            
            if ($driver !== 'pusher' || empty($pusherConfig['key'])) {
                return false;
            }
            
            // Additional connectivity tests could be added here
            return true;
            
        } catch (Exception $e) {
            Log::error('WebSocket connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get WebSocket configuration status
     */
    public function getConfigurationStatus(): array
    {
        $driver = config('broadcasting.default');
        $pusherConfig = config('broadcasting.connections.pusher');
        
        return [
            'driver' => $driver,
            'is_pusher_configured' => $driver === 'pusher' && !empty($pusherConfig['key']),
            'has_app_key' => !empty($pusherConfig['key']),
            'has_app_secret' => !empty($pusherConfig['secret']),
            'has_app_id' => !empty($pusherConfig['app_id']),
            'cluster' => $pusherConfig['options']['cluster'] ?? null,
        ];
    }
}