<?php

namespace App\Filament\Resources\GatewayResource\Pages;

use App\Filament\Resources\GatewayResource;
use App\Services\GatewayManagementService;
use App\Services\ModbusPollService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditGateway extends EditRecord
{
    protected static string $resource = GatewayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test_connection')
                ->label('Test Connection')
                ->icon('heroicon-o-signal')
                ->color('warning')
                ->action(function () {
                    $pollService = app(ModbusPollService::class);
                    $result = $pollService->testConnection(
                        $this->record->ip_address,
                        $this->record->port,
                        $this->record->unit_id
                    );
                    
                    if ($result->success) {
                        Notification::make()
                            ->title('Connection Test Successful')
                            ->body("Latency: {$result->latency}ms" . ($result->testValue !== null ? ", Test value: {$result->testValue}" : ''))
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Connection Test Failed')
                            ->body($result->error)
                            ->danger()
                            ->send();
                    }
                }),
            
            Actions\ViewAction::make(),
            
            Actions\DeleteAction::make()
                ->action(function () {
                    $service = app(GatewayManagementService::class);
                    $service->deleteGateway($this->record);
                    
                    return redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Validate the configuration using the service
        $service = app(GatewayManagementService::class);
        
        try {
            return $service->validateConfiguration($data, $this->record->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions to be handled by Filament
            throw $e;
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Gateway updated successfully';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}