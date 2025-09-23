<?php

namespace App\Livewire;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Services\ErrorHandlingService;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\On;

class LiveData extends Component
{
    public $dataPoints = [];
    public $filters = [
        'gateway' => null,
        'group' => null,
        'tag' => null,
    ];
    public $availableFilters = [
        'gateways' => [],
        'groups' => [],
        'tags' => [],
    ];
    public $density = 'comfortable'; // 'comfortable' or 'compact'
    public $refreshInterval = 5; // seconds - dynamically set based on gateway poll intervals
    public $emptyState = null;
    
    public function mount()
    {
        $this->loadAvailableFilters();
        $this->updateRefreshInterval();
        $this->loadLiveData();
    }
    
    #[On('reading-created')]
    #[On('gateway-updated')]
    #[On('echo:readings,reading.new')]
    #[On('echo:gateways,gateway.status-changed')]
    public function refreshLiveData()
    {
        $this->updateRefreshInterval();
        $this->loadLiveData();
    }
    
    public function loadAvailableFilters()
    {
        // Load available gateways
        $this->availableFilters['gateways'] = Gateway::active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
        
        // Load available groups
        $this->availableFilters['groups'] = DataPoint::enabled()
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name')
            ->toArray();
        
        // Load available tags (data types as tags)
        $this->availableFilters['tags'] = DataPoint::enabled()
            ->distinct()
            ->orderBy('data_type')
            ->pluck('data_type')
            ->toArray();
    }
    
    public function updateRefreshInterval()
    {
        // Get the poll interval from the filtered gateway or the minimum from all active gateways
        if ($this->filters['gateway']) {
            $gateway = Gateway::find($this->filters['gateway']);
            if ($gateway && $gateway->is_active) {
                $this->refreshInterval = $gateway->poll_interval;
                return;
            }
        }
        
        // If no specific gateway filter, use the minimum poll interval from all active gateways
        $minPollInterval = Gateway::active()->min('poll_interval');
        
        // Default to 5 seconds if no active gateways
        $this->refreshInterval = $minPollInterval ?: 5;
    }
    
    public function loadLiveData()
    {
        $this->checkEmptyState();
        
        if ($this->emptyState) {
            $this->dataPoints = [];
            return;
        }
        
        $query = DataPoint::with(['gateway', 'readings' => function ($query) {
            $query->latest('read_at')->limit(10);
        }])
        ->enabled()
        ->whereHas('gateway', function ($query) {
            $query->where('is_active', true);
        });
        
        // Apply filters
        if ($this->filters['gateway']) {
            $query->where('gateway_id', $this->filters['gateway']);
        }
        
        if ($this->filters['group']) {
            $query->where('group_name', $this->filters['group']);
        }
        
        if ($this->filters['tag']) {
            $query->where('data_type', $this->filters['tag']);
        }
        
        $dataPoints = $query->orderBy('gateway_id')
            ->orderBy('group_name')
            ->orderBy('label')
            ->get();
        
        $this->dataPoints = $dataPoints->map(function ($dataPoint) {
            $latestReading = $dataPoint->readings->first();
            $trendData = $dataPoint->readings->take(10)->reverse()->values();
            
            return [
                'id' => $dataPoint->id,
                'gateway_name' => $dataPoint->gateway->name,
                'gateway_id' => $dataPoint->gateway->id,
                'group_name' => $dataPoint->group_name,
                'label' => $dataPoint->label,
                'data_type' => $dataPoint->data_type,
                'register_address' => $dataPoint->register_address,
                'current_value' => $latestReading ? $latestReading->display_value : 'N/A',
                'quality' => $latestReading ? $latestReading->quality : 'unknown',
                'last_updated' => $latestReading ? $latestReading->read_at : null,
                'status' => $this->getDataPointStatus($dataPoint, $latestReading),
                'trend_data' => $trendData->map(function ($reading) {
                    return [
                        'value' => $reading->scaled_value,
                        'timestamp' => $reading->read_at->format('H:i:s'),
                        'quality' => $reading->quality,
                    ];
                })->toArray(),
                'is_enabled' => $dataPoint->is_enabled,
            ];
        })->toArray();
    }
    
    private function checkEmptyState()
    {
        $totalGateways = Gateway::count();
        
        if ($totalGateways === 0) {
            $this->emptyState = app(ErrorHandlingService::class)->getEmptyStateMessage('no_gateways');
            return;
        }
        
        $activeGateways = Gateway::active()->count();
        if ($activeGateways === 0) {
            $this->emptyState = app(ErrorHandlingService::class)->getEmptyStateMessage('gateway_offline', [
                'gateway_name' => 'All gateways'
            ]);
            return;
        }
        
        $enabledDataPoints = DataPoint::enabled()->count();
        if ($enabledDataPoints === 0) {
            $this->emptyState = app(ErrorHandlingService::class)->getEmptyStateMessage('no_data_points');
            return;
        }
        
        // Check if we have any data points matching current filters
        $query = DataPoint::enabled()->whereHas('gateway', function ($query) {
            $query->where('is_active', true);
        });
        
        if ($this->filters['gateway']) {
            $query->where('gateway_id', $this->filters['gateway']);
        }
        if ($this->filters['group']) {
            $query->where('group_name', $this->filters['group']);
        }
        if ($this->filters['tag']) {
            $query->where('data_type', $this->filters['tag']);
        }
        
        if ($query->count() === 0) {
            $this->emptyState = [
                'title' => 'No Data Points Match Filters',
                'message' => 'Try adjusting your filters or clear them to see all available data points.',
                'action_label' => 'Clear Filters',
                'action_url' => '#',
                'icon' => 'heroicon-o-funnel',
            ];
            return;
        }
        
        $this->emptyState = null;
    }
    
    private function getDataPointStatus($dataPoint, $latestReading)
    {
        if (!$latestReading) {
            return 'unknown';
        }
        
        if (!$dataPoint->gateway->is_online) {
            return 'down';
        }
        
        if ($latestReading->quality === 'good' && $latestReading->is_recent) {
            return 'up';
        }
        
        return 'unknown';
    }
    
    public function setFilter($type, $value)
    {
        $this->filters[$type] = $value === '' ? null : $value;
        
        // Update refresh interval when gateway filter changes
        if ($type === 'gateway') {
            $this->updateRefreshInterval();
        }
        
        $this->loadLiveData();
    }
    
    public function clearFilter($type)
    {
        $this->filters[$type] = null;
        $this->loadLiveData();
    }
    
    public function clearAllFilters()
    {
        $this->filters = [
            'gateway' => null,
            'group' => null,
            'tag' => null,
        ];
        
        // Update refresh interval when clearing gateway filter
        $this->updateRefreshInterval();
        $this->loadLiveData();
    }
    
    public function toggleDensity()
    {
        $this->density = $this->density === 'comfortable' ? 'compact' : 'comfortable';
    }
    
    public function getActiveFiltersProperty()
    {
        return collect($this->filters)->filter()->map(function ($value, $key) {
            $label = match($key) {
                'gateway' => collect($this->availableFilters['gateways'])->firstWhere('id', $value)['name'] ?? $value,
                'group' => $value,
                'tag' => $value,
                default => $value,
            };
            
            return [
                'type' => $key,
                'value' => $value,
                'label' => $label,
            ];
        })->values()->toArray();
    }
    
    public function render()
    {
        return view('livewire.live-data');
    }
}