<?php

namespace App\Filament\Widgets;

use App\Models\Gateway;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class GatewaysOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return Cache::remember('gateways_overview_stats', 60, function () {
            $gateways = Gateway::all();
            
            $online = $gateways->filter(function ($gateway) {
                return $gateway->last_seen_at && 
                       $gateway->last_seen_at->diffInMinutes(now()) <= 5;
            })->count();
            
            $offline = $gateways->count() - $online;
            
            $lastSync = $gateways->max('last_seen_at');
            $lastSyncFormatted = $lastSync ? $lastSync->diffForHumans() : 'Never';
            
            return [
                Stat::make('Online Registrations', $online)
                    ->description('Currently connected')
                    ->descriptionIcon('heroicon-m-arrow-trending-up')
                    ->color('success')
                    ->icon('heroicon-m-signal'),
                    
                Stat::make('Offline Registrations', $offline)
                    ->description('Not responding')
                    ->descriptionIcon('heroicon-m-arrow-trending-down')
                    ->color($offline > 0 ? 'danger' : 'gray')
                    ->icon('heroicon-m-no-symbol'),
                    
                Stat::make('Last Sync', $lastSyncFormatted)
                    ->description('Most recent activity')
                    ->descriptionIcon('heroicon-m-clock')
                    ->color('gray')
                    ->icon('heroicon-m-clock'),
            ];
        });
    }
}