<div class="space-y-6" wire:poll.30s="refreshDashboard" role="main" aria-label="Gateway monitoring dashboard">
    {{-- Skip to main content link --}}
    <a href="#main-content" class="skip-link">Skip to main content</a>
    
    <div id="main-content">
        @if($emptyState)
            {{-- Empty State --}}
            <div class="text-center py-12">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-100">
                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        @if($emptyState['icon'] === 'heroicon-o-server')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                        @elseif($emptyState['icon'] === 'heroicon-o-chart-bar')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        @elseif($emptyState['icon'] === 'heroicon-o-signal-slash')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        @else
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        @endif
                    </svg>
                </div>
                <h3 class="mt-4 text-lg font-medium text-gray-900">{{ $emptyState['title'] }}</h3>
                <p class="mt-2 text-sm text-gray-500 max-w-md mx-auto">{{ $emptyState['message'] }}</p>
                @if($emptyState['action_url'] && $emptyState['action_url'] !== '#')
                    <div class="mt-6">
                        <a href="{{ $emptyState['action_url'] }}" 
                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            {{ $emptyState['action_label'] }}
                        </a>
                    </div>
                @endif
            </div>
        @else
            {{-- Historical Data Notice --}}
            @php
                $hasHistoricalData = collect($weeklyMeterCards)->contains('is_historical', true) || 
                                   collect($recentEvents)->contains('is_historical', true) ||
                                   collect($gateways)->every(fn($g) => $g['status'] === 'offline');
            @endphp
            
            @if($hasHistoricalData)
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-amber-800">
                                Showing Historical Data
                            </h3>
                            <div class="mt-1 text-sm text-amber-700">
                                <p>Gateways are currently offline. Dashboard is displaying the most recent available data for monitoring purposes.</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="space-y-6">
                {{-- KPI Tiles --}}
                <section aria-labelledby="kpi-heading" class="fi-dashboard-tiles grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6">
                    <h2 id="kpi-heading" class="sr-only">Key Performance Indicators</h2>
                    {{-- Online Gateways KPI --}}
                    <div class="kpi-tile bg-white rounded-lg shadow-sm border border-gray-200 p-6" 
                         role="region" 
                         aria-labelledby="online-gateways-label"
                         {!! implode(' ', \App\Services\AccessibilityService::getLiveRegionAttributes('polite')) !!}>
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center
                                            {{ $kpis['online_gateways']['status'] === 'good' ? 'bg-green-100' : 
                                               ($kpis['online_gateways']['status'] === 'warning' ? 'bg-yellow-100' : 'bg-red-100') }}">
                                            <svg class="w-5 h-5 {{ $kpis['online_gateways']['status'] === 'good' ? 'text-green-600' : 
                                                     ($kpis['online_gateways']['status'] === 'warning' ? 'text-yellow-600' : 'text-red-600') }}" 
                                                 fill="currentColor" viewBox="0 0 20 20" 
                                                 aria-hidden="true"
                                                 role="img"
                                                 aria-label="Gateway status icon">
                                                <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-600" id="online-gateways-label">Online Gateways</p>
                                        <div class="flex items-baseline space-x-2">
                                            <p class="text-2xl font-bold text-gray-900" aria-labelledby="online-gateways-label">
                                                {{ $kpis['online_gateways']['value'] }}
                                            </p>
                                            <span class="text-sm text-gray-500">/ {{ $kpis['online_gateways']['total'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <div class="text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $kpis['online_gateways']['status'] === 'good' ? 'bg-green-100 text-green-800' : 
                                           ($kpis['online_gateways']['status'] === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}"
                                          aria-label="{{ $kpis['online_gateways']['percentage'] }}% of gateways are online">
                                        {{ $kpis['online_gateways']['percentage'] }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Poll Success Rate KPI --}}
                    <div class="kpi-tile bg-white rounded-lg shadow-sm border border-gray-200 p-6"
                         role="region" 
                         aria-labelledby="success-rate-label"
                         {!! implode(' ', \App\Services\AccessibilityService::getLiveRegionAttributes('polite')) !!}>
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center
                                            {{ $kpis['poll_success_rate']['status'] === 'good' ? 'bg-blue-100' : 
                                               ($kpis['poll_success_rate']['status'] === 'warning' ? 'bg-yellow-100' : 'bg-red-100') }}">
                                            <svg class="w-5 h-5 {{ $kpis['poll_success_rate']['status'] === 'good' ? 'text-blue-600' : 
                                                     ($kpis['poll_success_rate']['status'] === 'warning' ? 'text-yellow-600' : 'text-red-600') }}" 
                                                 fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"></path>
                                                <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-600" id="success-rate-label">Poll Success Rate</p>
                                        <p class="text-2xl font-bold text-gray-900" aria-labelledby="success-rate-label">
                                            {{ $kpis['poll_success_rate']['value'] }}%
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $kpis['poll_success_rate']['status'] === 'good' ? 'bg-green-100 text-green-800' : 
                                       ($kpis['poll_success_rate']['status'] === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    {{ $kpis['poll_success_rate']['status'] === 'good' ? 'Excellent' : 
                                       ($kpis['poll_success_rate']['status'] === 'warning' ? 'Good' : 'Poor') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Average Latency KPI --}}
                    <div class="kpi-tile bg-white rounded-lg shadow-sm border border-gray-200 p-6"
                         role="region" 
                         aria-labelledby="latency-label"
                         {!! implode(' ', \App\Services\AccessibilityService::getLiveRegionAttributes('polite')) !!}>
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center
                                            {{ $kpis['average_latency']['status'] === 'good' ? 'bg-green-100' : 
                                               ($kpis['average_latency']['status'] === 'warning' ? 'bg-yellow-100' : 'bg-red-100') }}">
                                            <svg class="w-5 h-5 {{ $kpis['average_latency']['status'] === 'good' ? 'text-green-600' : 
                                                     ($kpis['average_latency']['status'] === 'warning' ? 'text-yellow-600' : 'text-red-600') }}" 
                                                 fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-600" id="latency-label">Average Latency</p>
                                        <div class="flex items-baseline space-x-1">
                                            <p class="text-2xl font-bold text-gray-900" aria-labelledby="latency-label">
                                                {{ $kpis['average_latency']['value'] }}
                                            </p>
                                            <span class="text-sm text-gray-500">ms</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $kpis['average_latency']['status'] === 'good' ? 'bg-green-100 text-green-800' : 
                                       ($kpis['average_latency']['status'] === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    {{ $kpis['average_latency']['status'] === 'good' ? 'Fast' : 
                                       ($kpis['average_latency']['status'] === 'warning' ? 'Moderate' : 'Slow') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Fleet Status Strip --}}
                <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" 
                         aria-labelledby="fleet-status-heading"
                         role="region">
                    <div class="flex items-center justify-between mb-4">
                        <h3 id="fleet-status-heading" class="text-lg font-medium text-gray-900">Fleet Status</h3>
                        <span class="text-sm text-gray-500">{{ count($gateways) }} gateways</span>
                    </div>
                    
                    @if(empty($gateways))
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No gateways configured</h3>
                            <p class="mt-1 text-sm text-gray-500">Get started by adding your first Teltonika gateway.</p>
                            <div class="mt-6">
                                <a href="/admin/gateways/create" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                   aria-label="Add your first gateway to start monitoring">
                                    <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                    </svg>
                                    Add Gateway
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" 
                             role="list" 
                             aria-label="Gateway status cards">
                            @foreach($gateways as $gateway)
                                <div class="gateway-status-card bg-gray-50 rounded-lg p-4 border border-gray-200 hover:shadow-md transition-shadow duration-200 keyboard-focusable"
                                     role="listitem"
                                     tabindex="0"
                                     aria-label="Gateway {{ $gateway['name'] }} - Status: {{ $gateway['status'] }}, Success rate: {{ number_format($gateway['success_rate'], 1) }}%">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex-1 min-w-0">
                                            <h4 class="text-sm font-medium text-gray-900 truncate">{{ $gateway['name'] }}</h4>
                                            <p class="text-xs text-gray-500">{{ $gateway['ip_address'] }}:{{ $gateway['port'] }}</p>
                                        </div>
                                        <div class="flex-shrink-0 ml-2">
                                            <span class="status-indicator inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                {{ $gateway['status'] === 'online' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}"
                                                  aria-label="{!! \App\Services\AccessibilityService::getStatusAriaLabel($gateway['status']) !!}">
                                                <span class="status-dot w-1.5 h-1.5 rounded-full mr-1
                                                    {{ $gateway['status'] === 'online' ? 'bg-green-400 status-online' : 'bg-red-400 status-offline' }}" 
                                                      aria-hidden="true"></span>
                                                {{ ucfirst($gateway['status']) }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <div class="flex justify-between text-xs">
                                            <span class="text-gray-500">Success Rate:</span>
                                            <span class="font-medium {{ $gateway['success_rate'] >= 95 ? 'text-green-600' : ($gateway['success_rate'] >= 80 ? 'text-yellow-600' : 'text-red-600') }}">
                                                {{ number_format($gateway['success_rate'], 1) }}%
                                            </span>
                                        </div>
                                        
                                        <div class="flex justify-between text-xs">
                                            <span class="text-gray-500">Data Points:</span>
                                            <span class="font-medium text-gray-900">{{ $gateway['data_points_count'] }}</span>
                                        </div>
                                        
                                        @if($gateway['last_seen_at'])
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-500">Last Seen:</span>
                                                <span class="font-medium text-gray-900" title="{{ $gateway['last_seen_at'] }}">
                                                    {{ \Carbon\Carbon::parse($gateway['last_seen_at'])->diffForHumans() }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                    
                                    {{-- Mini Sparkline Chart --}}
                                    @if(!empty($gateway['sparkline_data']))
                                        <div class="mt-3 pt-3 border-t border-gray-200">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-xs text-gray-500">Activity (last hour)</span>
                                            </div>
                                            <div class="h-8 flex items-end space-x-1" 
                                                 role="img" 
                                                 aria-label="Activity sparkline chart for {{ $gateway['name'] }}"
                                                 tabindex="0">
                                                @foreach($gateway['sparkline_data'] as $value)
                                                    <div class="flex-1 bg-blue-200 rounded-sm min-h-1" 
                                                         style="height: {{ max(4, ($value / max(array_merge($gateway['sparkline_data'], [1]))) * 100) }}%"
                                                         title="Activity: {{ $value }}"></div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                {{-- Weekly Meter Cards --}}
                @if(!empty($weeklyMeterCards))
                    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" 
                             aria-labelledby="weekly-usage-heading"
                             role="region">
                        <div class="flex items-center justify-between mb-4">
                            <h3 id="weekly-usage-heading" class="text-lg font-medium text-gray-900">Weekly Usage (per meter)</h3>
                            <span class="text-sm text-gray-500">Last 7 days</span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" 
                             role="list" 
                             aria-label="Weekly meter usage cards">
                            @foreach($weeklyMeterCards as $card)
                                <div class="meter-usage-card bg-gray-50 rounded-lg p-4 border border-gray-200 hover:shadow-md transition-shadow duration-200"
                                     role="listitem"
                                     aria-label="Meter {{ $card['label'] }} - Weekly usage: {{ number_format($card['total_usage'], 2) }} {{ $card['unit'] }}">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex-1 min-w-0">
                                            <h4 class="text-sm font-medium text-gray-900 truncate">{{ $card['label'] }}</h4>
                                            <p class="text-xs text-gray-500">{{ $card['gateway_name'] }}</p>
                                        </div>
                                        <div class="flex-shrink-0 ml-2">
                                            <span class="usage-indicator inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                {{ $card['color'] === 'green' ? 'bg-green-100 text-green-800' : 
                                                   ($card['color'] === 'yellow' ? 'bg-yellow-100 text-yellow-800' : 
                                                   ($card['color'] === 'red' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) }}">
                                                <span class="usage-dot w-1.5 h-1.5 rounded-full mr-1
                                                    {{ $card['color'] === 'green' ? 'bg-green-400' : 
                                                       ($card['color'] === 'yellow' ? 'bg-yellow-400' : 
                                                       ($card['color'] === 'red' ? 'bg-red-400' : 'bg-blue-400')) }}" 
                                                      aria-hidden="true"></span>
                                                {{ ucfirst($card['color']) }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-500">Total Usage:</span>
                                            <span class="font-bold text-gray-900">{{ number_format($card['total_usage'], 2) }} {{ $card['unit'] }}</span>
                                        </div>
                                        
                                        <div class="flex justify-between text-xs">
                                            <span class="text-gray-500">Daily Average:</span>
                                            <span class="font-medium text-gray-900">{{ number_format($card['average_daily'], 2) }} {{ $card['unit'] }}/day</span>
                                        </div>
                                    </div>
                                    
                                    {{-- Daily Usage Sparkline --}}
                                    @if(!empty($card['daily_usage']) && array_sum($card['daily_usage']) > 0)
                                        <div class="mt-3 pt-3 border-t border-gray-200">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-xs text-gray-500">Daily usage (7 days)</span>
                                            </div>
                                            <div class="h-8 flex items-end space-x-1" 
                                                 role="img" 
                                                 aria-label="Daily usage sparkline chart for {{ $card['label'] }}"
                                                 tabindex="0">
                                                @foreach($card['daily_usage'] as $value)
                                                    <div class="flex-1 rounded-sm min-h-1
                                                        {{ $card['color'] === 'green' ? 'bg-green-200' : 
                                                           ($card['color'] === 'yellow' ? 'bg-yellow-200' : 
                                                           ($card['color'] === 'red' ? 'bg-red-200' : 'bg-blue-200')) }}" 
                                                         style="height: {{ max(4, ($value / max(array_merge($card['daily_usage'], [1]))) * 100) }}%"
                                                         title="Day usage: {{ $value }} {{ $card['unit'] }}"></div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Recent Events Timeline --}}
                <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
                         aria-labelledby="recent-events-heading"
                         role="region">
                    <div class="flex items-center justify-between mb-4">
                        <h3 id="recent-events-heading" class="text-lg font-medium text-gray-900">Recent Events</h3>
                        <span class="text-sm text-gray-500">Last 24 hours</span>
                    </div>
                    
                    @if(empty($recentEvents))
                        <div class="text-center py-6">
                            <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="mt-2 text-sm text-gray-500">No recent events to display</p>
                        </div>
                    @else
                        <div class="flow-root">
                            <ul role="list" class="-mb-8">
                                @foreach($recentEvents as $index => $event)
                                    <li>
                                        <div class="relative pb-8">
                                            @if($index < count($recentEvents) - 1)
                                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                            @endif
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white
                                                        {{ $event['severity'] === 'error' ? 'bg-red-500' : 
                                                           ($event['severity'] === 'warning' ? 'bg-yellow-500' : 'bg-blue-500') }}">
                                                        @if($event['type'] === 'gateway_offline')
                                                            <svg class="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                            </svg>
                                                        @else
                                                            <svg class="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                                <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                                                            </svg>
                                                        @endif
                                                    </span>
                                                </div>
                                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                    <div>
                                                        <p class="text-sm text-gray-900">{{ $event['message'] }}</p>
                                                        <p class="text-xs text-gray-500">Gateway: {{ $event['gateway_name'] }}</p>
                                                    </div>
                                                    <div class="text-right text-xs text-gray-500 whitespace-nowrap">
                                                        <time datetime="{{ $event['timestamp'] }}" title="{{ $event['timestamp'] }}">
                                                            {{ \Carbon\Carbon::parse($event['timestamp'])->diffForHumans() }}
                                                        </time>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>
            </div>
        @endif
    </div>
    
    {{-- Inline script to avoid multiple root elements --}}
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // WebSocket connection status
        let isConnected = false;
        let fallbackInterval = null;
        
        // Initialize WebSocket listeners
        if (window.Echo) {
            // Listen for gateway status changes
            window.Echo.channel('gateways')
                .listen('.gateway.status-changed', (e) => {
                    console.log('Gateway status changed:', e);
                    @this.call('refreshDashboard');
                });
            
            // Listen for new readings
            window.Echo.channel('readings')
                .listen('.reading.new', (e) => {
                    console.log('New reading received:', e);
                    @this.call('refreshDashboard');
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
        
        // Fallback polling mechanism
        function startFallbackPolling() {
            if (fallbackInterval) return;
            
            console.log('Starting fallback polling');
            fallbackInterval = setInterval(() => {
                if (!isConnected) {
                    @this.call('refreshDashboard');
                }
            }, 10000); // Poll every 10 seconds when WebSocket is down
        }
        
        function clearFallbackPolling() {
            if (fallbackInterval) {
                console.log('Clearing fallback polling');
                clearInterval(fallbackInterval);
                fallbackInterval = null;
            }
        }
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            clearFallbackPolling();
        });
    });
    </script>
</div>