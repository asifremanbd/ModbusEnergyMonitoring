<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Weekly Usage Cards
        </x-slot>

        @if($this->getViewData()['hasData'])
            <x-filament::grid default="1" md="2" xl="4" class="gap-4">
                @foreach($this->getViewData()['devices'] as $device)
                    @php
                        $heroicons = [
                            'power' => 'heroicon-m-bolt',
                            'water' => 'heroicon-m-beaker',
                            'socket' => 'heroicon-m-power',
                            'radiator' => 'heroicon-m-fire',
                            'fan' => 'heroicon-m-arrow-path',
                            'faucet' => 'heroicon-m-beaker',
                            'ac' => 'heroicon-m-cpu-chip',
                            'other' => 'heroicon-m-chart-bar',
                        ];
                        
                        $colors = [
                            'power' => 'warning',
                            'water' => 'info',
                            'socket' => 'primary',
                            'radiator' => 'danger',
                            'fan' => 'success',
                            'faucet' => 'info',
                            'ac' => 'primary',
                            'other' => 'gray',
                        ];
                        
                        $chartColors = [
                            'power' => 'rgba(234, 179, 8, 0.8)',
                            'water' => 'rgba(59, 130, 246, 0.8)',
                            'socket' => 'rgba(147, 51, 234, 0.8)',
                            'radiator' => 'rgba(239, 68, 68, 0.8)',
                            'fan' => 'rgba(20, 184, 166, 0.8)',
                            'faucet' => 'rgba(14, 165, 233, 0.8)',
                            'ac' => 'rgba(99, 102, 241, 0.8)',
                            'other' => 'rgba(107, 114, 128, 0.8)',
                        ];
                        
                        $heroicon = $heroicons[$device['load_type']] ?? $heroicons['other'];
                        $color = $colors[$device['load_type']] ?? $colors['other'];
                        $chartColor = $chartColors[$device['load_type']] ?? $chartColors['other'];
                    @endphp
                    
                    <x-filament::card class="shadow-sm hover:shadow-md transition-shadow duration-200 dark:bg-gray-800">
                        <!-- Header with Device Name and Icon -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide">
                                    {{ $device['label'] }}
                                </h3>
                            </div>
                            <div class="ml-3">
                                <x-filament::icon 
                                    :icon="$heroicon"
                                    class="w-6 h-6 text-{{ $color }}-500 dark:text-{{ $color }}-400"
                                />
                            </div>
                        </div>
                        
                        <!-- Weekly Total -->
                        <div class="mb-2">
                            <div class="text-3xl font-bold text-gray-900 dark:text-gray-100 leading-none">
                                {{ number_format($device['total_usage'], 1) }}
                                <span class="text-lg font-medium text-gray-600 dark:text-gray-400 ml-1">{{ $device['unit'] }}</span>
                            </div>
                        </div>
                        
                        <!-- Daily Average -->
                        <div class="text-sm text-gray-600 dark:text-gray-400 font-medium mb-4">
                            Daily avg: {{ number_format($device['daily_average'], 1) }} {{ $device['unit'] }}/day
                        </div>
                        
                        <!-- Status Indicator -->
                        <div class="flex items-center justify-center">
                            @if($device['status'] === 'current')
                                <div class="flex items-center text-green-600 dark:text-green-400">
                                    <div class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                                    <span class="text-xs font-medium">Live Data</span>
                                </div>
                            @elseif($device['status'] === 'recent')
                                <div class="flex items-center text-blue-600 dark:text-blue-400">
                                    <div class="w-2 h-2 bg-blue-500 rounded-full mr-2"></div>
                                    <span class="text-xs font-medium">Recent Data</span>
                                </div>
                            @else
                                <div class="flex items-center text-amber-600 dark:text-amber-400">
                                    <div class="w-2 h-2 bg-amber-500 rounded-full mr-2"></div>
                                    <span class="text-xs font-medium">Historical Data</span>
                                </div>
                            @endif
                        </div>
                    </x-filament::card>
                @endforeach
            </x-filament::grid>
        @else
            <div class="text-center py-12">
                <x-filament::icon 
                    icon="heroicon-o-chart-bar-square"
                    class="w-12 h-12 text-gray-400 dark:text-gray-500 mx-auto mb-4"
                />
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No Usage Data Available</h3>
                <p class="text-gray-500 dark:text-gray-400">No monitoring devices found or no readings available.</p>
            </div>
        @endif
    </x-filament::section>

</x-filament-widgets::widget>