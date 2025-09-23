<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Session;

class UiSettingsService
{
    private const SESSION_KEY = 'ui_settings';
    private const REFRESH_INTERVAL_KEY = 'refresh_interval';
    private const LAST_UPDATED_KEY = 'last_updated';
    private const DEFAULT_INTERVAL = 5;
    
    private const VALID_INTERVALS = [0, 2, 5, 10, 30]; // 0 = Off
    
    /**
     * Set the global refresh interval in seconds
     */
    public function setRefreshInterval(int $seconds): void
    {
        if (!$this->isValidInterval($seconds)) {
            throw new \InvalidArgumentException("Invalid refresh interval: {$seconds}. Must be one of: " . implode(', ', self::VALID_INTERVALS));
        }
        
        $settings = $this->getSettings();
        $settings[self::REFRESH_INTERVAL_KEY] = $seconds;
        
        Session::put(self::SESSION_KEY, $settings);
    }
    
    /**
     * Get the current refresh interval in seconds
     */
    public function getRefreshInterval(): int
    {
        $settings = $this->getSettings();
        return $settings[self::REFRESH_INTERVAL_KEY] ?? self::DEFAULT_INTERVAL;
    }
    
    /**
     * Get available refresh interval options
     */
    public function getRefreshOptions(): array
    {
        return [
            0 => 'Off',
            2 => '2s',
            5 => '5s',
            10 => '10s',
            30 => '30s',
        ];
    }
    
    /**
     * Get the last updated timestamp
     */
    public function getLastUpdatedTimestamp(): ?Carbon
    {
        $settings = $this->getSettings();
        $timestamp = $settings[self::LAST_UPDATED_KEY] ?? null;
        
        return $timestamp ? Carbon::parse($timestamp) : null;
    }
    
    /**
     * Update the last refresh timestamp to now
     */
    public function updateLastRefresh(): void
    {
        $settings = $this->getSettings();
        $settings[self::LAST_UPDATED_KEY] = Carbon::now()->toISOString();
        
        Session::put(self::SESSION_KEY, $settings);
    }
    
    /**
     * Format the last updated time as relative time (e.g., "Updated 4s ago")
     */
    public function getFormattedLastUpdated(): string
    {
        $lastUpdated = $this->getLastUpdatedTimestamp();
        
        if (!$lastUpdated) {
            return 'Never updated';
        }
        
        $diffInSeconds = $lastUpdated->diffInSeconds(Carbon::now());
        
        if ($diffInSeconds < 60) {
            return "Updated {$diffInSeconds}s ago";
        } elseif ($diffInSeconds < 3600) {
            $minutes = floor($diffInSeconds / 60);
            return "Updated {$minutes}m ago";
        } else {
            $hours = floor($diffInSeconds / 3600);
            return "Updated {$hours}h ago";
        }
    }
    
    /**
     * Get the current refresh interval formatted for display (e.g., "Auto-refresh: 5s")
     */
    public function getFormattedRefreshInterval(): string
    {
        $interval = $this->getRefreshInterval();
        
        if ($interval === 0) {
            return 'Auto-refresh: Off';
        }
        
        return "Auto-refresh: {$interval}s";
    }
    
    /**
     * Check if refresh is currently enabled
     */
    public function isRefreshEnabled(): bool
    {
        return $this->getRefreshInterval() > 0;
    }
    
    /**
     * Get refresh interval in milliseconds for Livewire polling
     */
    public function getRefreshIntervalMs(): int
    {
        $interval = $this->getRefreshInterval();
        return $interval > 0 ? $interval * 1000 : 0;
    }
    
    /**
     * Validate if the given interval is allowed
     */
    private function isValidInterval(int $seconds): bool
    {
        return in_array($seconds, self::VALID_INTERVALS, true);
    }
    
    /**
     * Get all UI settings from session
     */
    private function getSettings(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }
}