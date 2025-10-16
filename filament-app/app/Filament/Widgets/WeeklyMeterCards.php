<?php

namespace App\Filament\Widgets;

use App\Models\DataPoint;
use App\Models\Reading;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class WeeklyMeterCards extends Widget
{
    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = 'full';
    
    // Cache for 1 minute, refresh every 30 minutes
    protected static ?string $pollingInterval = '30m';
    
    protected static string $view = 'filament.widgets.weekly-meter-cards';
    
    public function getViewData(): array
    {
        return Cache::remember('weekly_meter_cards_data', 60, function () {
            $iconMap = [
                'power' => 'power-meter.png',
                'water' => 'water-meter.png',
                'socket' => 'supply.png',
                'radiator' => 'radiator.png',
                'fan' => 'fan.png',
                'faucet' => 'faucet.png',
                'ac' => 'electric-meter.png',
                'other' => 'statistics.png',
            ];

            $dataPoints = DataPoint::where('application', 'monitoring')
                ->with(['readings' => function ($query) {
                    $query->where('quality', 'good')
                          ->orderBy('read_at');
                }])
                ->enabled()
                ->get();

            $devices = $dataPoints->map(function ($dataPoint) use ($iconMap) {
                $historicalData = $this->calculateHistoricalUsage($dataPoint);
                
                return [
                    'id' => $dataPoint->id,
                    'label' => $dataPoint->label,
                    'unit' => $dataPoint->unit ?? 'None',
                    'total_usage' => $historicalData['totalUsage'] ?? 0,
                    'daily_average' => $historicalData['dailyAverage'] ?? 0,
                    'icon' => $iconMap[$dataPoint->load_type] ?? 'statistics.png',
                    'load_type' => $dataPoint->load_type ?? 'other',
                    'has_readings' => $dataPoint->readings->count() > 0,
                    'last_reading_date' => $historicalData['lastReadingDate'],
                    'data_period' => $historicalData['dataPeriod'],
                    'status' => $historicalData['status'],
                ];
            })->values();

            return [
                'devices' => $devices,
                'hasData' => $devices->isNotEmpty(),
            ];
        });
    }
    
    protected function calculateHistoricalUsage(DataPoint $dataPoint): array
    {
        $readings = $dataPoint->readings;
        
        if ($readings->count() === 0) {
            return [
                'totalUsage' => 0,
                'dailyAverage' => 0,
                'lastReadingDate' => null,
                'dataPeriod' => null,
                'status' => 'no_data',
            ];
        }
        
        // Sort readings by timestamp
        $sortedReadings = $readings->sortBy('read_at');
        $lastReading = $sortedReadings->last();
        $lastReadingDate = $lastReading->read_at;
        
        // Determine data freshness
        $hoursAgo = now()->diffInHours($lastReadingDate);
        $status = 'current';
        if ($hoursAgo > 24) {
            $status = 'stale';
        } elseif ($hoursAgo > 2) {
            $status = 'recent';
        }
        
        // Try to get a week's worth of data, but use whatever is available
        $weekAgoTarget = now()->subDays(7);
        $recentReadings = $sortedReadings->filter(function ($reading) use ($weekAgoTarget) {
            return $reading->read_at >= $weekAgoTarget;
        });
        
        // If no recent readings, use the most recent available data
        if ($recentReadings->count() < 2) {
            $recentReadings = $sortedReadings->take(-30); // Last 30 readings
        }
        
        $totalUsage = 0;
        $dailyAverage = 0;
        $dataPeriod = 'No data';
        
        if ($recentReadings->count() >= 2) {
            $firstReading = $recentReadings->first();
            $lastReading = $recentReadings->last();
            
            // Calculate usage for cumulative meters
            if ($lastReading->scaled_value !== null && 
                $firstReading->scaled_value !== null && 
                $lastReading->scaled_value >= $firstReading->scaled_value) {
                $totalUsage = $lastReading->scaled_value - $firstReading->scaled_value;
                
                // Calculate daily average based on actual time period
                $daysDiff = $firstReading->read_at->diffInDays($lastReading->read_at);
                if ($daysDiff > 0) {
                    $dailyAverage = $totalUsage / $daysDiff;
                    $dataPeriod = $daysDiff . ' days';
                } else {
                    $dailyAverage = $totalUsage;
                    $dataPeriod = '< 1 day';
                }
            }
        } elseif ($readings->count() === 1) {
            // Single reading - show the value as current reading
            $totalUsage = $lastReading->scaled_value ?? 0;
            $dataPeriod = 'Single reading';
        }
        
        return [
            'totalUsage' => round($totalUsage, 2),
            'dailyAverage' => round($dailyAverage, 2),
            'lastReadingDate' => $lastReadingDate,
            'dataPeriod' => $dataPeriod,
            'status' => $status,
        ];
    }


}