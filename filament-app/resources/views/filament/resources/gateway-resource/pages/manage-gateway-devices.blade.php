<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Navigation Breadcrumb -->
        <div class="flex items-center space-x-2 text-sm text-gray-600 mb-4">
            <a href="{{ $this->getResource()::getUrl('index') }}" class="hover:text-gray-900 transition-colors">
                <x-heroicon-o-home class="w-4 h-4 inline mr-1" />
                Gateways
            </a>
            <x-heroicon-o-chevron-right class="w-4 h-4" />
            <span class="text-gray-900 font-medium">{{ $this->record->name }}</span>
        </div>

        <!-- Gateway Info Card -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <x-heroicon-o-server class="w-6 h-6 text-blue-600" />
                            </div>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ $this->record->name }}</h2>
                            <div class="flex items-center space-x-4 mt-1">
                                <p class="text-sm text-gray-600">{{ $this->record->ip_address }}:{{ $this->record->port }}</p>
                                @if($this->record->is_active)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <x-heroicon-o-check-circle class="w-3 h-3 mr-1" />
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <x-heroicon-o-x-circle class="w-3 h-3 mr-1" />
                                        Inactive
                                    </span>
                                @endif
                                @if($this->record->last_seen_at)
                                    <span class="text-xs text-gray-500">
                                        Last seen: {{ $this->record->last_seen_at->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ $this->getTableQuery()->count() }}</div>
                        <div class="text-xs text-gray-500">Devices</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">{{ $this->record->devices()->withCount('registers')->get()->sum('registers_count') }}</div>
                        <div class="text-xs text-gray-500">Total Registers</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-600">{{ $this->record->devices()->where('enabled', true)->count() }}</div>
                        <div class="text-xs text-gray-500">Active Devices</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Devices Table -->
        {{ $this->table }}
    </div>
</x-filament-panels::page>