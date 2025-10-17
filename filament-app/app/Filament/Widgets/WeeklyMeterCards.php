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
                    'today_usage' => $historicalData['todayUsage'] ?? 0,
                    'weekly_total' => $historicalData['weeklyTotal'] ?? 0,
                    'weekly_average' => $historicalData['weeklyAverage'] ?? 0,
                    'current_value' => $historicalData['currentValue'] ?? 0,
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
                'todayUsage' => 0,
                'weeklyTotal' => 0,
                'weeklyAverage' => 0,
                'currentValue' => 0,
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
        
        // Calculate different time periods
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $weekStart = $now->copy()->startOfWeek();
        
        // Get readings for different periods
        $todayReadings = $sortedReadings->filter(function ($reading) use ($todayStart) {
            return $reading->read_at >= $todayStart;
        });
        
        $weekReadings = $sortedReadings->filter(function ($reading) use ($weekStart) {
            return $reading->read_at >= $weekStart;
        });
        
        // Calculate total usage (all time)
        $totalUsage = 0;
        if ($sortedReadings->count() >= 2) {
            $firstReading = $sortedReadings->first();
            $lastReading = $sortedReadings->last();
            
            if ($lastReading->scaled_value !== null && 
                $firstReading->scaled_value !== null && 
                $lastReading->scaled_value >= $firstReading->scaled_value) {
                $totalUsage = $lastReading->scaled_value - $firstReading->scaled_value;
            }
        } elseif ($readings->count() === 1) {
            $totalUsage = $lastReading->scaled_value ?? 0;
        }
        
        // Calculate today's usage
        $todayUsage = 0;
        if ($todayReadings->count() >= 2) {
            $firstTodayReading = $todayReadings->first();
            $lastTodayReading = $todayReadings->last();
            
            if ($lastTodayReading->scaled_value !== null && 
                $firstTodayReading->scaled_value !== null && 
                $lastTodayReading->scaled_value >= $firstTodayReading->scaled_value) {
                $todayUsage = $lastTodayReading->scaled_value - $firstTodayReading->scaled_value;
            }
        }
        
        // Calculate weekly total
        $weeklyTotal = 0;
        if ($weekReadings->count() >= 2) {
            $firstWeekReading = $weekReadings->first();
            $lastWeekReading = $weekReadings->last();
            
            if ($lastWeekReading->scaled_value !== null && 
                $firstWeekReading->scaled_value !== null && 
                $lastWeekReading->scaled_value >= $firstWeekReading->scaled_value) {
                $weeklyTotal = $lastWeekReading->scaled_value - $firstWeekReading->scaled_value;
            }
        }
        
        // Calculate weekly average (based on weekly data)
        $weeklyAverage = 0;
        if ($weekReadings->count() >= 2) {
            $firstWeekReading = $weekReadings->first();
            $daysDiff = $firstWeekReading->read_at->diffInDays($now);
            if ($daysDiff > 0) {
                $weeklyAverage = $weeklyTotal / $daysDiff;
            } else {
                $weeklyAverage = $weeklyTotal;
            }
        }
        
        // Get current reading value (for amps or other instantaneous values)
        $currentValue = $lastReading->scaled_value ?? 0;
        
        $dataPeriod = $sortedReadings->count() . ' readings';
        
        return [
            'totalUsage' => round($totalUsage, 2),
            'todayUsage' => round($todayUsage, 2),
            'weeklyTotal' => round($weeklyTotal, 2),
            'weeklyAverage' => round($weeklyAverage, 2),
            'currentValue' => round($currentValue, 2),
            'lastReadingDate' => $lastReadingDate,
            'dataPeriod' => $dataPeriod,
            'status' => $status,
        ];
    }


}