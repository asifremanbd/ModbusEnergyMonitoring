<div class="space-y-6" role="main" aria-label="Past readings interface">
    {{-- Skip to main content link --}}
    <a href="#past-readings-content" class="skip-link">Skip to past readings content</a>
    
    {{-- Header with statistics --}}
    <header class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4" 
            id="past-readings-content" 
            role="banner" 
            aria-label="Past readings page header">
        <div class="flex flex-col gap-2">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Past Readings</h1>
            
            {{-- Success/Fail Statistics --}}
            <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                <span class="font-medium">
                    Success: <span class="text-green-600 dark:text-green-400 font-mono">{{ number_format($statistics['success_count']) }}</span>
                    · 
                    Fail: <span class="text-red-600 dark:text-red-400 font-mono">{{ number_format($statistics['fail_count']) }}</span>
                </span>
                @if($statistics['total_count'] > 0)
                    <span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                        {{ $statistics['success_rate'] }}% success rate
                    </span>
                @endif
            </div>
        </div>
        
        {{-- Date Range Quick Filters --}}
        <div class="flex flex-wrap gap-2">
            <button 
                wire:click="setDateRange('last_hour')"
                class="px-3 py-1 text-xs font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
                Last Hour
            </button>
            <button 
                wire:click="setDateRange('last_24h')"
                class="px-3 py-1 text-xs font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
                Last 24h
            </button>
            <button 
                wire:click="setDateRange('last_week')"
                class="px-3 py-1 text-xs font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
                Last Week
            </button>
            <button 
                wire:click="setDateRange('last_month')"
                class="px-3 py-1 text-xs font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
                Last Month
            </button>
        </div>
    </header>
    
    {{-- Filters Section --}}
    <section class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4"
             aria-labelledby="filters-heading"
             role="search">
        <h2 id="filters-heading" class="sr-only">Reading Filters</h2>
        
        {{-- Filter Controls Row 1 --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            {{-- Gateway Filter --}}
            <div>
                <label for="gateway-filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Gateway</label>
                <select 
                    id="gateway-filter"
                    wire:change="setFilter('gateway', $event.target.value)"
                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                >
                    <option value="">All Gateways</option>
                    @foreach($availableFilters['gateways'] as $gateway)
                        <option value="{{ $gateway['id'] }}" {{ $filters['gateway'] == $gateway['id'] ? 'selected' : '' }}>
                            {{ $gateway['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            {{-- Group Filter --}}
            <div>
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
            
            {{-- Data Point Filter --}}
            <div>
                <label for="data-point-filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data Point</label>
                <select 
                    id="data-point-filter"
                    wire:change="setFilter('data_point', $event.target.value)"
                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                >
                    <option value="">All Data Points</option>
                    @foreach($availableFilters['data_points'] as $dataPoint)
                        <option value="{{ $dataPoint['id'] }}" {{ $filters['data_point'] == $dataPoint['id'] ? 'selected' : '' }}>
                            {{ $dataPoint['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            {{-- Quality Filter --}}
            <div>
                <label for="quality-filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quality</label>
                <select 
                    id="quality-filter"
                    wire:change="setFilter('quality', $event.target.value)"
                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                >
                    <option value="">All Qualities</option>
                    @foreach($availableFilters['qualities'] as $quality)
                        <option value="{{ $quality }}" {{ $filters['quality'] == $quality ? 'selected' : '' }}>
                            {{ ucfirst($quality) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        
        {{-- Date Range Filters --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label for="date-from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
                <input 
                    type="datetime-local" 
                    id="date-from"
                    wire:model.live.debounce.500ms="filters.date_from"
                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                >
            </div>
            <div>
                <label for="date-to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                <input 
                    type="datetime-local" 
                    id="date-to"
                    wire:model.live.debounce.500ms="filters.date_to"
                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                >
            </div>
        </div>
        
        {{-- Filter Actions --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            {{-- Active Filter Chips --}}
            @if(count($this->activeFilters) > 0)
                <div class="flex flex-wrap gap-2"
                     role="list"
                     aria-label="Active filters">
                    @foreach($this->activeFilters as $filter)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200"
                              role="listitem">
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
            
            {{-- Clear All Button --}}
            @if(count($this->activeFilters) > 0)
                <button 
                    wire:click="clearAllFilters"
                    class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                    Clear All Filters
                </button>
            @endif
        </div>
    </section>
    
    {{-- Data Table --}}
    <section class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        @if($readings->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button wire:click="sortBy('read_at')" class="flex items-center space-x-1 hover:text-gray-700 dark:hover:text-gray-200">
                                    <span>Timestamp</span>
                                    @if($sortField === 'read_at')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if($sortDirection === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            @endif
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Gateway
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Data Point
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button wire:click="sortBy('scaled_value')" class="flex items-center space-x-1 hover:text-gray-700 dark:hover:text-gray-200">
                                    <span>Value</span>
                                    @if($sortField === 'scaled_value')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if($sortDirection === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            @endif
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button wire:click="sortBy('quality')" class="flex items-center space-x-1 hover:text-gray-700 dark:hover:text-gray-200">
                                    <span>Quality</span>
                                    @if($sortField === 'quality')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if($sortDirection === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            @endif
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Raw Value
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($readings as $reading)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                {{-- Timestamp --}}
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <div>{{ $reading->read_at->format('M j, Y H:i:s') }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $reading->read_at->diffForHumans() }}
                                    </div>
                                </td>
                                
                                {{-- Gateway --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $reading->dataPoint->gateway->name }}
                                    </div>
                                </td>
                                
                                {{-- Data Point --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $reading->dataPoint->label }}
                                    </div>
                                    @if($reading->dataPoint->group_name)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $reading->dataPoint->group_name }}
                                        </div>
                                    @endif
                                </td>
                                
                                {{-- Scaled Value --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-mono text-gray-900 dark:text-white">
                                        {{ $reading->display_value }}
                                    </div>
                                </td>
                                
                                {{-- Quality --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $reading->quality === 'good' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                           ($reading->quality === 'bad' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200') }}">
                                        {{ ucfirst($reading->quality) }}
                                    </span>
                                </td>
                                
                                {{-- Raw Value --}}
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500 dark:text-gray-400">
                                    {{ $reading->raw_value }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            {{-- Pagination --}}
            <div class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">
                {{ $readings->links() }}
            </div>
        @else
            {{-- Empty State --}}
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No readings found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if(count($this->activeFilters) > 0 || $filters['date_from'] || $filters['date_to'])
                        Try adjusting your filters or date range to see more results.
                    @else
                        No readings have been recorded yet.
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
            </div>
        @endif
    </section>
</div>