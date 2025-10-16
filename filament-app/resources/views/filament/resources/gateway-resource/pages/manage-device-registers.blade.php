<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Navigation Breadcrumb -->
        <div class="flex items-center space-x-2 text-sm text-gray-600 mb-4">
            <a href="{{ $this->getResource()::getUrl('index') }}" class="hover:text-gray-900 transition-colors">
                <x-heroicon-o-home class="w-4 h-4 inline mr-1" />
                Gateways
            </a>
            <x-heroicon-o-chevron-right class="w-4 h-4" />
            <a href="{{ App\Filament\Resources\GatewayResource\Pages\ManageGatewayDevices::getUrl(['record' => $this->gateway->id]) }}" class="hover:text-gray-900 transition-colors">
                {{ $this->gateway->name }}
            </a>
            <x-heroicon-o-chevron-right class="w-4 h-4" />
            <span class="text-gray-900 font-medium">{{ $this->device->device_name }}</span>
        </div>

        <!-- Gateway Context Card -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 border border-blue-200">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <x-heroicon-o-server class="w-5 h-5 text-blue-600" />
                    </div>
                </div>
                <div class="flex-1">
                    <div class="flex items-center space-x-4">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Gateway: {{ $this->gateway->name }}</p>
                            <p class="text-xs text-gray-600">{{ $this->gateway->ip_address }}:{{ $this->gateway->port }}</p>
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $this->gateway->devices()->count() }} devices • 
                            {{ $this->gateway->devices()->withCount('registers')->get()->sum('registers_count') }} total registers
                        </div>
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <a href="{{ App\Filament\Resources\GatewayResource\Pages\ManageGatewayDevices::getUrl(['record' => $this->gateway->id]) }}" 
                       class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View All Devices →
                    </a>
                </div>
            </div>
        </div>

        <!-- Device Info Card -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <x-heroicon-o-cpu-chip class="w-6 h-6 text-green-600" />
                            </div>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ $this->device->device_name }}</h2>
                            <div class="flex items-center space-x-4 mt-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $this->device->device_type_name }}
                                </span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ $this->device->load_category_name }}
                                </span>
                                @if($this->device->enabled)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <x-heroicon-o-check-circle class="w-3 h-3 mr-1" />
                                        Enabled
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <x-heroicon-o-x-circle class="w-3 h-3 mr-1" />
                                        Disabled
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ $this->device->registers()->count() }}</div>
                        <div class="text-xs text-gray-500">Total Registers</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">{{ $this->device->registers()->where('enabled', true)->count() }}</div>
                        <div class="text-xs text-gray-500">Active Registers</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-orange-600">{{ $this->device->registers()->where('enabled', false)->count() }}</div>
                        <div class="text-xs text-gray-500">Disabled Registers</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registers Table -->
        {{ $this->table }}
    </div>
</x-filament-panels::page>