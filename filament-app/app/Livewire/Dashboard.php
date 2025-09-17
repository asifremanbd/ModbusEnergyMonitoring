<?php

namespace App\Livewire;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Services\ErrorHandlingService;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\On;

class Dashboard extends Component
{
    public $kpis = [];
    public $gateways = [];
    public $recentEvents = [];
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
    
    public function render()
    {
        return view('livewire.dashboard');
    }
}
