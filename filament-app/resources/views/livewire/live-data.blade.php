<div class="space-y-6" role="main" aria-label="Live data readings interface">
    {{-- Skip to main content link --}}
    <a href="#live-data-content" class="skip-link">Skip to live data content</a>
    
    {{-- Header with filters and controls --}}
    <header class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4" 
            id="live-data-content" 
            role="banner" 
            aria-label="Live data page header">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Live Data Readings</h1>
            
            {{-- Density Toggle --}}
            <button 
                wire:click="toggleDensity"
                class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                aria-label="Toggle between comfortable and compact table view"
                aria-pressed="{{ $density === 'compact' ? 'true' : 'false' }}"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                </svg>
                {{ $density === 'comfortable' ? 'Compact View' : 'Comfortable View' }}
            </button>
        </div>
        
        {{-- Auto-refresh indicator --}}
        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400"
             {!! implode(' ', \App\Services\AccessibilityService::getLiveRegionAttributes('polite')) !!}>
            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse mr-2"></div>
            Auto-refreshing every {{ $refreshInterval }}s
        </div>
    </header>
    
    {{-- Filters Section --}}
    <section class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4"
             aria-labelledby="filters-heading"
             role="search">
        <h2 id="filters-heading" class="sr-only">Data Filters</h2>
        <div class="flex flex-col lg:flex-row lg:items-center gap-4">
            {{-- Filter Controls --}}
            <div class="filter-controls flex flex-col sm:flex-row gap-3 flex-1">
                {{-- Gateway Filter --}}
                <div class="min-w-0 flex-1">
                    <label for="gateway-filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Gateway</label>
                    <select 
                        id="gateway-filter"
                        wire:change="setFilter('gateway', $event.target.value)"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        aria-describedby="gateway-filter-help"
                    >
                        <option value="">All Gateways</option>
                        @foreach($availableFilters['gateways'] as $gateway)
                            <option value="{{ $gateway['id'] }}" {{ $filters['gateway'] == $gateway['id'] ? 'selected' : '' }}>
                                {{ $gateway['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div id="gateway-filter-help" class="sr-only">Filter data points by gateway</div>
                </div>
                
                {{-- Group Filter --}}
                <div class="min-w-0 flex-1">
                    <label for="group-filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Group</label>
                    <select 
                        id="group-filter"
                        wire:change="setFilter('group', $event.target.value)"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    >
                        <option value="">All Groups</option>
                        @foreach($availableFilters['groups'] as $group)
                            <option value="{{ $group }}" {{ $filters['group'] == $group ? 'selected' : '' }}>
                                {{ $group }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                {{-- Tag Filter --}}
                <div class="min-w-0 flex-1">
                    <label for="tag-filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data Type</label>
                    <select 
                        id="tag-filter"
                        wire:change="setFilter('tag', $event.target.value)"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    >
                        <option value="">All Types</option>
                        @foreach($availableFilters['tags'] as $tag)
                            <option value="{{ $tag }}" {{ $filters['tag'] == $tag ? 'selected' : '' }}>
                                {{ ucfirst($tag) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            
            {{-- Clear Filters Button --}}
            @if(count($this->activeFilters) > 0)
                <button 
                    wire:click="clearAllFilters"
                    class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    aria-label="Clear all active filters"
                >
                    Clear All
                </button>
            @endif
        </div>
        
        {{-- Active Filter Chips --}}
        @if(count($this->activeFilters) > 0)
            <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-gray-200 dark:border-gray-600"
                 role="list"
                 aria-label="Active filters">
                @foreach($this->activeFilters as $filter)
                    <span class="filter-chip inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200"
                          role="listitem"
                          aria-label="Active filter: {{ ucfirst($filter['type']) }} is {{ $filter['label'] }}">
                        {{ ucfirst($filter['type']) }}: {{ $filter['label'] }}
                        <button 
                            wire:click="clearFilter('{{ $filter['type'] }}')"
                            class="ml-2 inline-flex items-center justify-center w-4 h-4 rounded-full text-blue-600 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            aria-label="Remove {{ ucfirst($filter['type']) }} filter"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </span>
                @endforeach
            </div>
        @endif
    </section>
    
    {{-- Data Table --}}
    <section class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden"
             aria-labelledby="data-table-heading"
             role="region">
        <h2 id="data-table-heading" class="sr-only">Live Data Readings Table</h2>
        @if(count($dataPoints) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                       {!! implode(' ', \App\Services\AccessibilityService::getTableAttributes('Live data readings from gateways', count($dataPoints), 7)) !!}>
                    {{-- Sticky Header --}}
                    <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">
                        <tr>
                            <th {!! implode(' ', \App\Services\AccessibilityService::getTableHeaderAttributes('Gateway')) !!} 
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Gateway
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Group
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Data Point
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Current Value
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Trend (Last 10)
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Last Updated
                            </th>
                        </tr>
                    </thead>
                    
                    {{-- Table Body --}}
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($dataPoints as $dataPoint)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 {{ $density === 'compact' ? 'h-12' : 'h-16' }}">
                                {{-- Gateway --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $dataPoint['gateway_name'] }}
                                    </div>
                                </td>
                                
                                {{-- Group --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        {{ $dataPoint['group_name'] }}
                                    </div>
                                </td>
                                
                                {{-- Data Point --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $dataPoint['label'] }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Reg: {{ $dataPoint['register_address'] }} | {{ ucfirst($dataPoint['data_type']) }}
                                    </div>
                                </td>
                                
                                {{-- Current Value --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-mono text-gray-900 dark:text-white">
                                        {{ $dataPoint['current_value'] }}
                                    </div>
                                </td>
                                
                                {{-- Status --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center">
                                        <div class="w-2 h-2 rounded-full mr-2 {{ 
                                            $dataPoint['status'] === 'up' ? 'bg-green-500' : 
                                            ($dataPoint['status'] === 'down' ? 'bg-red-500' : 'bg-gray-400') 
                                        }}"></div>
                                        <span class="text-sm text-gray-900 dark:text-white">
                                            {{ ucfirst($dataPoint['status']) }}
                                        </span>
                                    </span>
                                </td>
                                
                                {{-- Mini Trend Chart --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="w-24 h-8">
                                        @if(count($dataPoint['trend_data']) > 1)
                                            <canvas 
                                                class="sparkline-chart" 
                                                data-values="{{ json_encode(collect($dataPoint['trend_data'])->pluck('value')->toArray()) }}"
                                                data-labels="{{ json_encode(collect($dataPoint['trend_data'])->pluck('timestamp')->toArray()) }}"
                                                width="96" 
                                                height="32"
                                            ></canvas>
                                        @else
                                            <div class="text-xs text-gray-400 dark:text-gray-500">No trend data</div>
                                        @endif
                                    </div>
                                </td>
                                
                                {{-- Last Updated --}}
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    @if($dataPoint['last_updated'])
                                        <div>{{ $dataPoint['last_updated']->format('H:i:s') }}</div>
                                        <div class="text-xs">{{ $dataPoint['last_updated']->diffForHumans() }}</div>
                                    @else
                                        <div>Never</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            {{-- Empty State --}}
            <div class="text-center py-12">
                @if($emptyState)
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-100">
                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            @if($emptyState['icon'] === 'heroicon-o-server')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                            @elseif($emptyState['icon'] === 'heroicon-o-chart-bar')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            @elseif($emptyState['icon'] === 'heroicon-o-funnel')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            @else
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            @endif
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">{{ $emptyState['title'] }}</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">{{ $emptyState['message'] }}</p>
                    @if($emptyState['action_url'] && $emptyState['action_url'] !== '#')
                        <div class="mt-6">
                            <a href="{{ $emptyState['action_url'] }}" 
                               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                {{ $emptyState['action_label'] }}
                            </a>
                        </div>
                    @elseif($emptyState['action_label'] === 'Clear Filters')
                        <div class="mt-6">
                            <button 
                                wire:click="clearAllFilters"
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                {{ $emptyState['action_label'] }}
                            </button>
                        </div>
                    @endif
                @else
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No data points found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        @if(count($this->activeFilters) > 0)
                            Try adjusting your filters or add data points to your gateways.
                        @else
                            Get started by adding data points to your gateways.
                        @endif
                    </p>
                    @if(count($this->activeFilters) > 0)
                        <div class="mt-6">
                            <button 
                                wire:click="clearAllFilters"
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                Clear Filters
                            </button>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </section>
    
    {{-- Auto-refresh and WebSocket script --}}
    <script>
        // WebSocket connection status
        let isConnected = false;
        let fallbackInterval = null;
        
        // Initialize WebSocket listeners
        document.addEventListener('DOMContentLoaded', function() {
            if (window.Echo) {
                // Listen for new readings
                window.Echo.channel('readings')
                    .listen('.reading.new', (e) => {
                        console.log('New reading received:', e);
                        @this.call('refreshLiveData');
                    });
                
                // Listen for gateway status changes
                window.Echo.channel('gateways')
                    .listen('.gateway.status-changed', (e) => {
                        console.log('Gateway status changed:', e);
                        @this.call('refreshLiveData');
                    });
                
                // Connection status monitoring
                window.Echo.connector.pusher.connection.bind('connected', () => {
                    console.log('WebSocket connected');
                    isConnected = true;
                    clearFallbackPolling();
                });
                
                window.Echo.connector.pusher.connection.bind('disconnected', () => {
                    console.log('WebSocket disconnected');
                    isConnected = false;
                    startFallbackPolling();
                });
                
                window.Echo.connector.pusher.connection.bind('error', (error) => {
                    console.error('WebSocket error:', error);
                    isConnected = false;
                    startFallbackPolling();
                });
            } else {
                console.warn('Echo not available, using fallback polling');
                startFallbackPolling();
            }
        });
        
        // Fallback polling mechanism
        function startFallbackPolling() {
            if (fallbackInterval) return;
            
            console.log('Starting fallback polling for live data');
            fallbackInterval = setInterval(() => {
                if (!isConnected && typeof Livewire !== 'undefined') {
                    @this.call('refreshLiveData');
                }
            }, {{ $refreshInterval * 1000 }});
        }
        
        function clearFallbackPolling() {
            if (fallbackInterval) {
                console.log('Clearing fallback polling for live data');
                clearInterval(fallbackInterval);
                fallbackInterval = null;
            }
        }
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            clearFallbackPolling();
        });
        
        // Initialize sparkline charts after each Livewire update
        document.addEventListener('livewire:navigated', initializeSparklines);
        document.addEventListener('DOMContentLoaded', initializeSparklines);
        
        function initializeSparklines() {
            const charts = document.querySelectorAll('.sparkline-chart');
            charts.forEach(canvas => {
                const values = JSON.parse(canvas.dataset.values || '[]');
                const labels = JSON.parse(canvas.dataset.labels || '[]');
                
                if (values.length < 2) return;
                
                const ctx = canvas.getContext('2d');
                const width = canvas.width;
                const height = canvas.height;
                
                // Clear canvas
                ctx.clearRect(0, 0, width, height);
                
                // Calculate min/max for scaling
                const min = Math.min(...values);
                const max = Math.max(...values);
                const range = max - min || 1;
                
                // Draw sparkline
                ctx.strokeStyle = '#3B82F6'; // Blue color
                ctx.lineWidth = 1.5;
                ctx.beginPath();
                
                values.forEach((value, index) => {
                    const x = (index / (values.length - 1)) * (width - 4) + 2;
                    const y = height - 2 - ((value - min) / range) * (height - 4);
                    
                    if (index === 0) {
                        ctx.moveTo(x, y);
                    } else {
                        ctx.lineTo(x, y);
                    }
                });
                
                ctx.stroke();
                
                // Draw dots for data points
                ctx.fillStyle = '#3B82F6';
                values.forEach((value, index) => {
                    const x = (index / (values.length - 1)) * (width - 4) + 2;
                    const y = height - 2 - ((value - min) / range) * (height - 4);
                    
                    ctx.beginPath();
                    ctx.arc(x, y, 1, 0, 2 * Math.PI);
                    ctx.fill();
                });
            });
        }
    </script>
</div>