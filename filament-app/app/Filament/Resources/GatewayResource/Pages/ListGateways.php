<?php

namespace App\Filament\Resources\GatewayResource\Pages;

use App\Filament\Resources\GatewayResource;
use App\Services\UiSettingsService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListGateways extends ListRecords
{
    protected static string $resource = GatewayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Modbus Registration')
                ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * Get the refresh interval in milliseconds for Livewire polling
     */
    public function getRefreshIntervalMs(): int
    {
        $uiSettingsService = app(UiSettingsService::class);
        return $uiSettingsService->getRefreshIntervalMs();
    }

    /**
     * Check if refresh is enabled
     */
    public function isRefreshEnabled(): bool
    {
        $uiSettingsService = app(UiSettingsService::class);
        return $uiSettingsService->isRefreshEnabled();
    }

    /**
     * Listen for global refresh interval changes
     */
    #[On('refresh-interval-changed')]
    public function onRefreshIntervalChanged(): void
    {
        // Force a re-render to update the polling interval
        $this->dispatch('$refresh');
    }

    /**
     * Update last refresh timestamp when data is refreshed
     */
    public function updatedPaginators(): void
    {
        parent::updatedPaginators();
        
        if ($this->isRefreshEnabled()) {
            $uiSettingsService = app(UiSettingsService::class);
            $uiSettingsService->updateLastRefresh();
        }
    }

    /**
     * Override the render method to include polling
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        $view = parent::render();
        
        // Update last refresh timestamp when rendering with active polling
        if ($this->isRefreshEnabled()) {
            $uiSettingsService = app(UiSettingsService::class);
            $uiSettingsService->updateLastRefresh();
        }
        
        return $view;
    }
}