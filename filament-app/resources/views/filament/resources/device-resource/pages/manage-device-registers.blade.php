<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Device Information Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Device Name</h3>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $device->device_name }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Gateway</h3>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $device->gateway->name }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $device->gateway->ip_address }}:{{ $device->gateway->port }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Type & Category</h3>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $device->device_type_name }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $device->load_category_name }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Register Statistics</h3>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $device->enabled_registers_count }}/{{ $device->registers_count }} Active
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @if($device->registers_count > 0)
                            {{ round(($device->enabled_registers_count / $device->registers_count) * 100, 1) }}% enabled
                        @else
                            No registers configured
                        @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- Registers List --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Registers</h2>
            @if($device->registers->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Label</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Function</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scale</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enabled</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($device->registers as $register)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $register->id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">{{ $register->technical_label }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($register->count > 1)
                                            {{ $register->register_address }}-{{ $register->register_address + $register->count - 1 }}
                                        @else
                                            {{ $register->register_address }}
                                        @endif
                                        <div class="text-xs text-gray-500">Count: {{ $register->count }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            @if($register->function == 1) bg-green-100 text-green-800
                                            @elseif($register->function == 2) bg-blue-100 text-blue-800
                                            @elseif($register->function == 3) bg-yellow-100 text-yellow-800
                                            @elseif($register->function == 4) bg-purple-100 text-purple-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            {{ $register->function }} - {{ $register->function_name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $register->data_type }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $register->scale }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($register->enabled)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                ✓ Enabled
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                ✗ Disabled
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500">No registers found for this device.</p>
            @endif
        </div>
    </div>
</x-filament-panels::page>