<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Usage Cards
        </x-slot>

        @if($this->getViewData()['hasData'])
            <div class="flex flex-row gap-4 overflow-x-auto">
                @foreach($this->getViewData()['devices'] as $device)
                    @php
                        $iconMap = [
                            'power' => 'power-meter.png',
                            'water' => 'water-meter.png',
                            'socket' => 'supply.png',
                            'radiator' => 'radiator.png',
                            'fan' => 'fan.png',
                            'faucet' => 'faucet.png',
                            'ac' => 'fan.png', // Using fan icon for AC as it's similar
                            'other' => 'statistics.png',
                        ];
                        
                        $iconFile = $iconMap[$device['load_type']] ?? $iconMap['other'];
                        
                        // Determine if we should show amps (for electrical devices)
                        $showAmps = in_array($device['load_type'], ['power', 'socket', 'ac']) && $device['unit'] === 'kWh';
                        $ampsValue = $showAmps ? round($device['current_value'] * 4.35, 1) : null; // Rough conversion for display
                    @endphp
                    
                    <div class="relative bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:shadow-lg transition-all duration-200 hover:-translate-y-1 flex-1 min-w-0">
                        <!-- Large Background Icon -->
                        <div class="absolute top-2 right-2 opacity-25 dark:opacity-20 z-0" style="right: 8px; top: 8px;">
                            <img 
                                src="{{ asset('images/icons/' . $iconFile) }}" 
                                alt="{{ $device['label'] }} icon"
                                class="w-20 h-20 object-contain"
                            />
                        </div>
                        
                        <!-- Title -->
                        <div class="relative z-10 mb-2">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $device['label'] }}
                            </h3>
                        </div>
                        
                        <!-- Big Total Value -->
                        <div class="relative z-10 mb-4">
                            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {{ number_format($device['total_usage'], 1) }}
                                <span class="text-sm font-medium text-gray-500 dark:text-gray-400 ml-1">{{ $device['unit'] }}</span>
                            </div>
                        </div>
                        
                        <!-- Metrics Rows -->
                        <div class="relative z-10 space-y-2 mb-4">
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500 dark:text-gray-400">Today</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ number_format($device['today_usage'], 1) }} {{ $device['unit'] }}
                                </span>
                            </div>
                            
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500 dark:text-gray-400">Weekly Total</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ number_format($device['weekly_total'], 1) }} {{ $device['unit'] }}
                                </span>
                            </div>
                            
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500 dark:text-gray-400">Weekly Avg</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ number_format($device['weekly_average'], 1) }} {{ $device['unit'] }}
                                </span>
                            </div>
                            
                            @if($showAmps && $ampsValue)
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500 dark:text-gray-400">Amps</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $ampsValue }} A
                                </span>
                            </div>
                            @endif
                        </div>
                        
                        <!-- Last Updated Footer -->
                        <div class="relative z-10 pt-2 border-t border-gray-100 dark:border-gray-700">
                            <div class="text-xs text-gray-400 dark:text-gray-500">
                                @if($device['last_reading_date'])
                                    {{ $device['last_reading_date']->diffForHumans() }}
                                @else
                                    No recent data
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
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