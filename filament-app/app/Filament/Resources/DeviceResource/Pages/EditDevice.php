<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('manage_registers')
                ->label('Manage Registers')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('primary')
                ->url(fn (): string => DeviceResource::getUrl('manage-registers', ['device' => $this->record->id])),
            
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Device')
                ->modalDescription('This will permanently delete the device and all its registers. This action cannot be undone.')
                ->modalSubmitActionLabel('Delete Device'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Validate device name uniqueness within gateway (excluding current device)
        try {
            $validationErrors = \App\Models\Device::validateData($data, $data['gateway_id'], $this->record->id);
            
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

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Device updated successfully';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
