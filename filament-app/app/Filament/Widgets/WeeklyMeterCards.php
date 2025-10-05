<?php

namespace App\Filament\Widgets;

use App\Models\DataPoint;
use App\Models\Reading;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class WeeklyMeterCards extends BaseWidget
{
    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = 'full';
    
    // Cache for 5 minutes to improve performance
    protected static ?string $pollingInterval = '30s';
    
    protected function getStats(): array
    {
        return Cache::remember('weekly_meter_cards_stats', 300, function () {
            $dataPoints = DataPoint::with(['gateway', 'readings' => function ($query) {
                $query->where('read_at', '>=', now()->subDays(8))
                      ->where('quality', 'good')
                      ->orderBy('read_at');
            }])
            ->enabled()
            ->get();

            $stats = [];
            
            foreach ($dataPoints as $dataPoint) {
                $weeklyData = $this->calculateWeeklyUsage($dataPoint);
                
                if ($weeklyData['totalUsage'] !== null && $weeklyData['totalUsage'] > 0) {
                    $stats[] = $this->createMeterStat($dataPoint, $weeklyData);
                }
            }
            
            return empty($stats) ? [$this->createNoDataStat()] : $stats;
        });
    }
    
    protected function calculateWeeklyUsage(DataPoint $dataPoint): array
    {
        $readings = $dataPoint->readings;
        
        if ($readings->count() < 2) {
            return [
                'totalUsage' => null,
                'dailyUsage' => [],
                'unit' => $this->getUnit($dataPoint),
            ];
        }
        
        // Sort readings by timestamp
        $sortedReadings = $readings->sortBy('read_at');
        
        // Group readings by day and calculate daily consumption
        $dailyUsage = [];
        $totalUsage = 0;
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayStart = Carbon::parse($date)->startOfDay();
            $dayEnd = Carbon::parse($date)->endOfDay();
            
            $dayReadings = $sortedReadings->filter(function ($reading) use ($dayStart, $dayEnd) {
                return $reading->read_at->between($dayStart, $dayEnd);
            });
            
            $dayUsage = 0;
            if ($dayReadings->count() >= 2) {
                $firstReading = $dayReadings->first();
                $lastReading = $dayReadings->last();
                
                // Handle cumulative meter readings - consumption is the difference
                if ($lastReading->scaled_value !== null && 
                    $firstReading->scaled_value !== null && 
                    $lastReading->scaled_value >= $firstReading->scaled_value) {
                    $dayUsage = $lastReading->scaled_value - $firstReading->scaled_value;
                    $totalUsage += $dayUsage;
                }
            }
            
            $dailyUsage[] = round($dayUsage, 2);
        }
        
        return [
            'totalUsage' => round($totalUsage, 2),
            'dailyUsage' => $dailyUsage,
            'unit' => $this->getUnit($dataPoint),
        ];
    }
    
    protected function getUnit(DataPoint $dataPoint): string
    {
        $label = strtolower($dataPoint->label);
        $groupName = strtolower($dataPoint->group_name);
        $searchText = $label . ' ' . $groupName;
        
        // Energy-related keywords
        $energyKeywords = ['energy', 'kwh', 'kw-h', 'kilowatt', 'watt', 'power', 'electricity', 'electric'];
        foreach ($energyKeywords as $keyword) {
            if (str_contains($searchText, $keyword)) {
                return 'kWh';
            }
        }
        
        // Water-related keywords
        $waterKeywords = ['water', 'm³', 'm3', 'cubic', 'liter', 'litre', 'gallon', 'flow'];
        foreach ($waterKeywords as $keyword) {
            if (str_contains($searchText, $keyword)) {
                return 'm³';
            }
        }
        
        // Gas-related keywords
        $gasKeywords = ['gas', 'natural gas', 'propane', 'methane'];
        foreach ($gasKeywords as $keyword) {
            if (str_contains($searchText, $keyword)) {
                return 'm³';
            }
        }
        
        return 'units';
    }
    
    protected function createMeterStat(DataPoint $dataPoint, array $weeklyData): Stat
    {
        $gatewayName = $dataPoint->gateway->name ?? 'Unknown Gateway';
        $averageDaily = $weeklyData['totalUsage'] / 7;
        
        $stat = Stat::make(
            $dataPoint->label,
            number_format($weeklyData['totalUsage'], 2) . ' ' . $weeklyData['unit']
        )
        ->description("Avg: " . number_format($averageDaily, 2) . ' ' . $weeklyData['unit'] . '/day • ' . $gatewayName)
        ->descriptionIcon('heroicon-m-chart-bar')
        ->color($this->getColorForUsage($weeklyData['totalUsage'], $weeklyData['unit']));
        
        // Add sparkline chart if we have daily data
        if (!empty($weeklyData['dailyUsage']) && array_sum($weeklyData['dailyUsage']) > 0) {
            $stat->chart($weeklyData['dailyUsage']);
        }
        
        return $stat;
    }
    
    protected function getColorForUsage(float $usage, string $unit): string
    {
        // Color coding based on usage levels (can be customized)
        if ($unit === 'kWh') {
            if ($usage > 100) return 'danger';
            if ($usage > 50) return 'warning';
            return 'success';
        }
        
        if ($unit === 'm³') {
            if ($usage > 50) return 'danger';
            if ($usage > 25) return 'warning';
            return 'success';
        }
        
        return 'primary';
    }
    
    protected function createNoDataStat(): Stat
    {
        return Stat::make('No Data Available', 'No meter readings found')
            ->description('Check your data points and readings')
            ->descriptionIcon('heroicon-m-exclamation-triangle')
            ->color('warning');
    }
    
    public function getHeading(): ?string
    {
        return 'Weekly Usage (per meter)';
    }
}