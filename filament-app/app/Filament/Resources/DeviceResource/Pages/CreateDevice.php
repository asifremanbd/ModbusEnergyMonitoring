<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateDevice extends CreateRecord
{
    protected static string $resource = DeviceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validate device name uniqueness within gateway
        try {
            $validationErrors = \App\Models\Device::validateData($data, $data['gateway_id']);
            
            if (!empty($validationErrors)) {
                $firstError = collect($validationErrors)->flatten()->first();
                Notification::make()
                    ->title('Validation Error')
                    ->body($firstError)
                    ->danger()
                    ->send();
                
                $this->halt();
            }
        } catch (\Exception $e) {
            app(\App\Services\FormExceptionHandlerService::class)->handleDeviceFormException($e);
            $this->halt();
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Device created successfully - registers can be added via the manage registers page
        Notification::make()
            ->title('Device Created')
            ->body("Device '{$this->record->device_name}' has been created. You can now add registers to it.")
            ->success()
            ->actions([
                \Filament\Notifications\Actions\Action::make('manage_registers')
                    ->label('Manage Registers')
                    ->url(DeviceResource::getUrl('manage-registers', ['device' => $this->record->id]))
                    ->button(),
            ])
            ->send();
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return null; // We handle this in afterCreate
    }

    protected function getRedirectUrl(): string
    {
        return DeviceResource::getUrl('manage-registers', ['device' => $this->record->id]);
    }
}
