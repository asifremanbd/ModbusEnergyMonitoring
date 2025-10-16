<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use Filament\Resources\Pages\Page;

class ManageDeviceRegistersSimple extends Page
{
    protected static string $resource = DeviceResource::class;

    protected static string $view = 'filament.resources.device-resource.pages.manage-device-registers-simple';

    public Device $device;

    public function getTitle(): string
    {
        return "Manage Registers (Simple)";
    }

    public function getHeading(): string
    {
        return "Registers for {$this->device->device_name}";
    }

    public function mount(int|string $device): void
    {
        $this->device = Device::with(['gateway', 'registers'])->findOrFail($device);
    }
}