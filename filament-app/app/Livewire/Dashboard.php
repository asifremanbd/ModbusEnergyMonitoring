<?php

namespace App\Livewire;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Services\ErrorHandlingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\On;

class Dashboard extends Component
{
    public $kpis = [];
    public $gateways = [];
    public $recentEvents = [];
    public $weeklyMeterCards = [];
    public $emptyState = null;
    
    public function mount()
    {
        $this->loadDashboardData();
    }
    
    #[On('gateway-updated')]
    #[On('reading-created')]
    #[On('echo:gateways,gateway.status-changed')]
    #[On('echo:readings,reading.new')]
    public function refreshDashboard()
    {
        $this->loadDashboardData();
    }
    
    public function loadDashboardData()
    {
        $this->checkEmptyState();
        
        if (!$this->emptyState) {
            $this->loadKpis();
            $this->loadGateways();
            $this->loadWeeklyMeterCards();
            $this->loadRecentEvents();
        }
    }
    
    private function checkEmptyState()
    {
        $totalGateways = Gateway::count();
        
        if ($totalGateways === 0) {
            $this->emptyState = app(ErrorHandlingService::class)->getEmptyStateMessage('no_gateways');
            return;
        }
        
        $activeDataPoints = DataPoint::enabled()->count();
        if ($activeDataPoints === 0) {
            $this->emptyState = app(ErrorHandlingService::class)->getEmptyStateMessage('no_data_points');
            return;
        }
        
        $recentReadings = Reading::where('read_at', '>=', now()->subHour())->count();
        if ($recentReadings === 0) {
            $this->emptyState = app(ErrorHandlingService::class)->getEmptyStateMessage('no_readings');
            return;
        }
        
        $this->emptyState = null;
    }
    
    private function loadKpis()
    {
        $totalGateways = Gateway::count();
        $onlineGateways = Gateway::whereHas('dataPoints', function ($query) {
            $query->where('is_enabled', true);
        })->get()->filter(function ($gateway) {
            return $gateway->is_online;
        })->count();
        
        // Calculate poll success rate from last 24 hours
        $recentReadings = Reading::where('read_at', '>=', now()->subDay())->get();
        $successRate = $recentReadings->isEmpty() ? 0 : 
            ($recentReadings->where('quality', 'good')->count() / $recentReadings->count()) * 100;
        
        // Calculate average latency from gateway success/failure counts
        $activeGateways = Gateway::active()->get();
        $avgLatency = $activeGateways->isEmpty() ? 0 : 
            $activeGateways->avg(function ($gateway) {
                // Estimate latency based on poll interval and success rate
                $baseLatency = $gateway->poll_interval * 1000 * 0.1; // 10% of poll interval as base
                $failureMultiplier = $gateway->failure_count > 0 ? 
                    (1 + ($gateway->failure_count / max($gateway->success_count, 1))) : 1;
                return min($baseLatency * $failureMultiplier, 5000); // Cap at 5 seconds
            });
        
        $this->kpis = [
            'online_gateways' => [
                'value' => $onlineGateways,
                'total' => $totalGateways,
                'percentage' => $totalGateways > 0 ? round(($onlineGateways / $totalGateways) * 100) : 0,
                'status' => $onlineGateways === $totalGateways ? 'good' : ($onlineGateways > 0 ? 'warning' : 'error')
            ],
            'poll_success_rate' => [
                'value' => round($successRate, 1),
                'status' => $successRate >= 95 ? 'good' : ($successRate >= 80 ? 'warning' : 'error')
            ],
            'average_latency' => [
                'value' => round($avgLatency),
                'status' => $avgLatency <= 1000 ? 'good' : ($avgLatency <= 3000 ? 'warning' : 'error')
            ]
        ];
    }
    
    private function loadGateways()
    {
        $this->gateways = Gateway::with(['dataPoints' => function ($query) {
            $query->where('is_enabled', true);
        }])->get()->map(function ($gateway) {
            $recentReadings = Reading::whereHas('dataPoint', function ($query) use ($gateway) {
                $query->where('gateway_id', $gateway->id);
            })->where('read_at', '>=', now()->subHour())
            ->orderBy('read_at')
            ->get();
            
            // Generate sparkline data (last 10 data points)
            $sparklineData = $recentReadings->groupBy(function ($reading) {
                return $reading->read_at->format('Y-m-d H:i');
            })->take(-10)->map(function ($readings) {
                return $readings->where('quality', 'good')->count();
            })->values()->toArray();
            
            return [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'ip_address' => $gateway->ip_address,
                'port' => $gateway->port,
                'is_online' => $gateway->is_online,
                'last_seen_at' => $gateway->last_seen_at,
                'success_rate' => $gateway->success_rate,
                'data_points_count' => $gateway->dataPoints->count(),
                'sparkline_data' => $sparklineData,
                'status' => $gateway->is_online ? 'online' : 'offline'
            ];
        })->toArray();
    }
    
    private function loadRecentEvents()
    {
        // Get gateways that went offline in the last 24 hours
        $offlineEvents = Gateway::where('last_seen_at', '>=', now()->subDay())
            ->where('last_seen_at', '<=', now()->subMinutes(5))
            ->get()
            ->map(function ($gateway) {
                return [
                    'type' => 'gateway_offline',
                    'message' => "Gateway '{$gateway->name}' went offline",
                    'gateway_name' => $gateway->name,
                    'timestamp' => $gateway->last_seen_at,
                    'severity' => 'error'
                ];
            });
        
        // Get recently updated gateways (configuration changes)
        $configEvents = Gateway::where('updated_at', '>=', now()->subDay())
            ->where('updated_at', '!=', Gateway::raw('created_at'))
            ->get()
            ->map(function ($gateway) {
                return [
                    'type' => 'configuration_changed',
                    'message' => "Gateway '{$gateway->name}' configuration updated",
                    'gateway_name' => $gateway->name,
                    'timestamp' => $gateway->updated_at,
                    'severity' => 'info'
                ];
            });
        
        $this->recentEvents = $offlineEvents->concat($configEvents)
            ->sortByDesc('timestamp')
            ->take(10)
            ->values()
            ->toArray();
    }
    
    private function loadWeeklyMeterCards()
    {
        $dataPoints = DataPoint::with(['gateway', 'readings' => function ($query) {
            $query->where('read_at', '>=', now()->subDays(8))
                  ->where('quality', 'good')
                  ->orderBy('read_at');
        }])
        ->enabled()
        ->get();

        $this->weeklyMeterCards = [];
        
        foreach ($dataPoints as $dataPoint) {
            $weeklyData = $this->calculateWeeklyUsage($dataPoint);
            
            if ($weeklyData['totalUsage'] !== null && $weeklyData['totalUsage'] > 0) {
                $this->weeklyMeterCards[] = [
                    'id' => $dataPoint->id,
                    'label' => $dataPoint->label,
                    'gateway_name' => $dataPoint->gateway->name ?? 'Unknown Gateway',
                    'total_usage' => $weeklyData['totalUsage'],
                    'unit' => $weeklyData['unit'],
                    'daily_usage' => $weeklyData['dailyUsage'],
                    'average_daily' => round($weeklyData['totalUsage'] / 7, 2),
                    'color' => $this->getColorForUsage($weeklyData['totalUsage'], $weeklyData['unit'])
                ];
            }
        }
    }
    
    private function calculateWeeklyUsage(DataPoint $dataPoint): array
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
    
    private function getUnit(DataPoint $dataPoint): string
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
    
    private function getColorForUsage(float $usage, string $unit): string
    {
        // Color coding based on usage levels (can be customized)
        if ($unit === 'kWh') {
            if ($usage > 100) return 'red';
            if ($usage > 50) return 'yellow';
            return 'green';
        }
        
        if ($unit === 'm³') {
            if ($usage > 50) return 'red';
            if ($usage > 25) return 'yellow';
            return 'green';
        }
        
        return 'blue';
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
