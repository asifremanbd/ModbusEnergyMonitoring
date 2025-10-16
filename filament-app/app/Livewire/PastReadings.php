<?php

namespace App\Livewire;

use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Services\ErrorHandlingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Carbon\Carbon;

class PastReadings extends Component
{
    use WithPagination;

    public $filters = [
        'gateway' => null,
        'group' => null,
        'data_point' => null,
        'quality' => null,
        'date_from' => null,
        'date_to' => null,
    ];
    
    public $availableFilters = [
        'gateways' => [],
        'groups' => [],
        'data_points' => [],
        'qualities' => ['good', 'bad', 'uncertain'],
    ];
    
    public $statistics = [
        'success_count' => 0,
        'fail_count' => 0,
        'total_count' => 0,
        'success_rate' => 0,
    ];
    
    public $perPage = 50;
    public $sortField = 'read_at';
    public $sortDirection = 'desc';
    public $emptyState = null;
    
    protected $queryString = [
        'filters' => ['except' => []],
        'page' => ['except' => 1],
        'perPage' => ['except' => 50],
        'sortField' => ['except' => 'read_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount()
    {
        // Set default date range to last 7 days to show existing data
        $this->filters['date_from'] = now()->subWeek()->format('Y-m-d H:i');
        $this->filters['date_to'] = now()->format('Y-m-d H:i');
        
        $this->loadAvailableFilters();
        $this->loadStatistics();
    }

    public function loadAvailableFilters()
    {
        // Load available gateways
        $this->availableFilters['gateways'] = Gateway::active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
        
        // Load available groups (applications)
        $this->availableFilters['groups'] = DataPoint::enabled()
            ->distinct()
            ->orderBy('application')
            ->pluck('application')
            ->filter()
            ->toArray();
        
        // Load available data points based on current gateway filter
        if ($this->filters['gateway']) {
            $this->availableFilters['data_points'] = DataPoint::enabled()
                ->where('gateway_id', $this->filters['gateway'])
                ->orderBy('label')
                ->get(['id', 'label', 'application', 'unit', 'load_type'])
                ->map(function ($dp) {
                    $application = ucfirst($dp->application ?: 'monitoring');
                    $customLabel = $dp->label ?: 'Unnamed';
                    return [
                        'id' => $dp->id,
                        'label' => "({$application}) - {$customLabel}",
                    ];
                })
                ->toArray();
        } else {
            $this->availableFilters['data_points'] = DataPoint::enabled()
                ->with('gateway:id,name')
                ->orderBy('label')
                ->get(['id', 'label', 'application', 'unit', 'load_type', 'gateway_id'])
                ->map(function ($dp) {
                    $application = ucfirst($dp->application ?: 'monitoring');
                    $customLabel = $dp->label ?: 'Unnamed';
                    $label = "({$application}) - {$customLabel}";
                    return [
                        'id' => $dp->id,
                        'label' => "{$dp->gateway->name} - {$label}",
                    ];
                })
                ->toArray();
        }
    }

    public function loadStatistics()
    {
        $cacheKey = $this->getStatisticsCacheKey();
        
        $this->statistics = Cache::remember($cacheKey, 300, function () {
            return $this->computeStatistics();
        });
    }



    private function computeStatistics(): array
    {
        $query = $this->getBaseQuery();
        
        // Get total count
        $totalCount = $query->count();
        
        if ($totalCount === 0) {
            return [
                'success_count' => 0,
                'fail_count' => 0,
                'total_count' => 0,
                'success_rate' => 0,
            ];
        }
        
        // Get success/fail counts
        $qualityCounts = $query->select('quality', DB::raw('count(*) as count'))
            ->groupBy('quality')
            ->pluck('count', 'quality')
            ->toArray();
        
        $successCount = $qualityCounts['good'] ?? 0;
        $failCount = ($qualityCounts['bad'] ?? 0) + ($qualityCounts['uncertain'] ?? 0);
        $successRate = $totalCount > 0 ? round(($successCount / $totalCount) * 100, 1) : 0;
        
        return [
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'total_count' => $totalCount,
            'success_rate' => $successRate,
        ];
    }

    private function getBaseQuery()
    {
        $query = Reading::with(['dataPoint.gateway']);
        
        // Apply filters
        if ($this->filters['gateway']) {
            $query->whereHas('dataPoint', function ($q) {
                $q->where('gateway_id', $this->filters['gateway']);
            });
        }
        
        if ($this->filters['group']) {
            $query->whereHas('dataPoint', function ($q) {
                $q->where('application', $this->filters['group']);
            });
        }
        
        if ($this->filters['data_point']) {
            $query->where('data_point_id', $this->filters['data_point']);
        }
        
        if ($this->filters['quality']) {
            $query->where('quality', $this->filters['quality']);
        }
        
        if ($this->filters['date_from']) {
            $query->where('read_at', '>=', Carbon::parse($this->filters['date_from']));
        }
        
        if ($this->filters['date_to']) {
            $query->where('read_at', '<=', Carbon::parse($this->filters['date_to']));
        }
        
        return $query;
    }

    public function setFilter($type, $value)
    {
        $this->filters[$type] = $value === '' ? null : $value;
        
        // Clear dependent filters
        if ($type === 'gateway') {
            $this->filters['data_point'] = null;
            $this->loadAvailableFilters();
        }
        
        $this->resetPage();
        $this->loadStatistics();
        $this->clearStatisticsCache();
    }

    public function clearFilter($type)
    {
        $this->filters[$type] = null;
        
        // Clear dependent filters
        if ($type === 'gateway') {
            $this->filters['data_point'] = null;
            $this->loadAvailableFilters();
        }
        
        $this->resetPage();
        $this->loadStatistics();
        $this->clearStatisticsCache();
    }

    public function clearAllFilters()
    {
        $dateFrom = $this->filters['date_from'];
        $dateTo = $this->filters['date_to'];
        
        $this->filters = [
            'gateway' => null,
            'group' => null,
            'data_point' => null,
            'quality' => null,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
        
        $this->loadAvailableFilters();
        $this->resetPage();
        $this->loadStatistics();
        $this->clearStatisticsCache();
    }

    public function setDateRange($range)
    {
        $now = now();
        
        switch ($range) {
            case 'last_hour':
                $this->filters['date_from'] = $now->copy()->subHour()->format('Y-m-d H:i');
                $this->filters['date_to'] = $now->format('Y-m-d H:i');
                break;
            case 'last_24h':
                $this->filters['date_from'] = $now->copy()->subDay()->format('Y-m-d H:i');
                $this->filters['date_to'] = $now->format('Y-m-d H:i');
                break;
            case 'last_week':
                $this->filters['date_from'] = $now->copy()->subWeek()->format('Y-m-d H:i');
                $this->filters['date_to'] = $now->format('Y-m-d H:i');
                break;
            case 'last_month':
                $this->filters['date_from'] = $now->copy()->subMonth()->format('Y-m-d H:i');
                $this->filters['date_to'] = $now->format('Y-m-d H:i');
                break;
        }
        
        $this->resetPage();
        $this->loadStatistics();
        $this->clearStatisticsCache();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }
        
        $this->resetPage();
    }

    private function clearStatisticsCache()
    {
        $cacheKey = $this->getStatisticsCacheKey();
        Cache::forget($cacheKey);
    }

    public function getStatisticsCacheKey(): string
    {
        $filterHash = md5(serialize($this->filters));
        return "past_readings_stats_{$filterHash}";
    }

    public function getActiveFiltersProperty()
    {
        return collect($this->filters)
            ->filter(function ($value, $key) {
                return $value !== null && !in_array($key, ['date_from', 'date_to']);
            })
            ->map(function ($value, $key) {
                $label = match($key) {
                    'gateway' => collect($this->availableFilters['gateways'])->firstWhere('id', $value)['name'] ?? $value,
                    'group' => $value,
                    'data_point' => collect($this->availableFilters['data_points'])->firstWhere('id', $value)['label'] ?? $value,
                    'quality' => ucfirst($value),
                    default => $value,
                };
                
                return [
                    'type' => $key,
                    'value' => $value,
                    'label' => $label,
                ];
            })
            ->values()
            ->toArray();
    }

    public function render()
    {
        $query = $this->getBaseQuery();
        
        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);
        
        // Get paginated results
        $readings = $query->paginate($this->perPage);
        
        return view('livewire.past-readings', [
            'readings' => $readings,
        ]);
    }
}