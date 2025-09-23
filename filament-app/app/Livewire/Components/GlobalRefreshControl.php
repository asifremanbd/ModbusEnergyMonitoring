<?php

namespace App\Livewire\Components;

use App\Services\UiSettingsService;
use Livewire\Component;

class GlobalRefreshControl extends Component
{
    public int $currentInterval;
    public string $lastUpdated = '';
    
    public function mount()
    {
        $this->currentInterval = $this->getUiSettingsService()->getRefreshInterval();
        $this->updateLastUpdatedDisplay();
    }

    public function setInterval(int $seconds)
    {
        $this->currentInterval = $seconds;
        $this->getUiSettingsService()->setRefreshInterval($seconds);
        $this->getUiSettingsService()->updateLastRefresh();
        $this->updateLastUpdatedDisplay();
        
        // Emit event to notify other components of interval change
        $this->dispatch('refresh-interval-changed', interval: $seconds);
    }

    public function updateLastUpdatedDisplay()
    {
        $this->lastUpdated = $this->getFormattedLastUpdated();
    }

    protected function getUiSettingsService(): UiSettingsService
    {
        return app(UiSettingsService::class);
    }

    public function getFormattedLastUpdated(): string
    {
        $timestamp = $this->getUiSettingsService()->getLastUpdatedTimestamp();
        
        if (!$timestamp) {
            return '';
        }

        $diffInSeconds = now()->diffInSeconds($timestamp);
        
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

    public function getRefreshOptions(): array
    {
        return $this->getUiSettingsService()->getRefreshOptions();
    }

    public function getIntervalDisplay(): string
    {
        if ($this->currentInterval === 0) {
            return 'Auto-refresh: Off';
        }
        
        return "Auto-refresh: {$this->currentInterval}s";
    }

    public function render()
    {
        return view('livewire.components.global-refresh-control');
    }
}