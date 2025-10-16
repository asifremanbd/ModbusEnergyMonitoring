<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">User Management</h3>
                <p class="mt-1 text-sm text-gray-600">Manage system users - add, edit, and delete user accounts.</p>
            </div>
            <div class="p-6">
                {{ $this->table }}
            </div>
        </div>
    </div>
</x-filament-panels::page>