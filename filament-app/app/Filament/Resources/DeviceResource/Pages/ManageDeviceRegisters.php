<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use App\Models\Register;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class ManageDeviceRegisters extends Page
{

    protected static string $resource = DeviceResource::class;

    protected static string $view = 'filament.resources.device-resource.pages.manage-device-registers';

    public Device $device;

    public function getTitle(): string
    {
        return "Manage Registers";
    }

    public function getHeading(): string
    {
        return "Registers for {$this->device->device_name}";
    }

    public function getSubheading(): ?string
    {
        return "Gateway: {$this->device->gateway->name} | Type: {$this->device->device_type_name} | Category: {$this->device->load_category_name}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            DeviceResource::getUrl('index') => 'Devices',
            DeviceResource::getUrl('edit', ['record' => $this->device->id]) => $this->device->device_name,
            '#' => 'Registers',
        ];
    }

    public function mount(int|string $device): void
    {
        $this->device = Device::with(['gateway', 'registers'])->findOrFail($device);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_devices')
                ->label('← Back to Devices')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => DeviceResource::getUrl('index')),
        ];
    }
}