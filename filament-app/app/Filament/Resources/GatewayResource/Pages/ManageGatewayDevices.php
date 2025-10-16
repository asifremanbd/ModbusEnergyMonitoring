<?php

namespace App\Filament\Resources\GatewayResource\Pages;

use App\Filament\Resources\GatewayResource;
use App\Models\Gateway;
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
use App\Traits\PreservesNavigationState;
use App\Services\NavigationContextService;

class ManageGatewayDevices extends Page implements Tables\Contracts\HasTable, Forms\Contracts\HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;
    use PreservesNavigationState;

    protected static string $resource = GatewayResource::class;

    protected static string $view = 'filament.resources.gateway-resource.pages.manage-gateway-devices';

    public Gateway $record;

    public function getTitle(): string
    {
        return "Devices";
    }

    public function getHeading(): string
    {
        return app(NavigationContextService::class)->generatePageTitle('devices', $this->record);
    }

    public function getSubheading(): ?string
    {
        return app(NavigationContextService::class)->generatePageSubheading('devices', $this->record);
    }

    public function getBreadcrumbs(): array
    {
        return app(NavigationContextService::class)->generateBreadcrumbs('devices', $this->record);
    }

    protected function getNavigationContext(): array
    {
        return app(NavigationContextService::class)->generateNavigationContext('devices', $this->record);
    }

    public function mount(int|string $record): void
    {
        $this->record = Gateway::findOrFail($record);
        
        // Restore table state from session if available
        $this->restoreTableState();
    }

    protected function getStateIdentifier(): string
    {
        return "gateway_{$this->record->id}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add_device')
                ->label('Add Device')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->form([
                    Forms\Components\TextInput::make('device_name')
                        ->label('Device Name')
                        ->required()
                        ->placeholder('Living Room Heater')
                        ->helperText('User-friendly name for this device')
                        ->maxLength(255)
                        ->rules([
                            new \App\Rules\UniqueDeviceNameInGatewayRule($this->record->id),
                        ])
                        ->validationMessages([
                            'required' => 'Device name is required.',
                            'max' => 'Device name cannot exceed 255 characters.',
                        ])
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state) {
                            if ($state) {
                                $validationService = app(\App\Services\ValidationService::class);
                                if (!$validationService->isDeviceNameUniqueInGateway($state, $this->record->id)) {
                                    // This will be caught by the validation rule above
                                }
                            }
                        }),
                    
                    Forms\Components\Select::make('device_type')
                        ->label('Device Type')
                        ->options(Device::getDeviceTypeOptions())
                        ->default('energy_meter')
                        ->required(),
                    
                    Forms\Components\Select::make('load_category')
                        ->label('Load Category')
                        ->options(Device::getLoadCategoryOptions())
                        ->default('other')
                        ->required(),
                    
                    Forms\Components\Toggle::make('enabled')
                        ->label('Enabled')
                        ->default(true)
                        ->helperText('Enable this device for data collection'),
                ])
                ->action(function (array $data): void {
                    try {
                        // Validate device data
                        $validationErrors = Device::validateData([
                            'gateway_id' => $this->record->id,
                            'device_name' => $data['device_name'],
                            'device_type' => $data['device_type'],
                            'load_category' => $data['load_category'],
                            'enabled' => $data['enabled'] ?? true,
                        ], $this->record->id);

                        if (!empty($validationErrors)) {
                            $firstError = collect($validationErrors)->flatten()->first();
                            Notification::make()
                                ->title('Validation Error')
                                ->body($firstError)
                                ->danger()
                                ->send();
                            return;
                        }

                        // Create the device using the Device model
                        $device = Device::create([
                            'gateway_id' => $this->record->id,
                            'device_name' => $data['device_name'],
                            'device_type' => $data['device_type'],
                            'load_category' => $data['load_category'],
                            'enabled' => $data['enabled'] ?? true,
                        ]);

                        // Create a default register for the new device
                        Register::create([
                            'device_id' => $device->id,
                            'technical_label' => $data['device_name'] . '_Default_Register',
                            'function' => 4, // Input Registers
                            'register_address' => 1025,
                            'data_type' => 'float32',
                            'byte_order' => 'word_swap',
                            'scale' => 1.0,
                            'count' => 2,
                            'enabled' => true,
                        ]);

                        Notification::make()
                            ->title('Device Created')
                            ->body("Device '{$data['device_name']}' has been created with a default register.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        app(\App\Services\FormExceptionHandlerService::class)->handleDeviceFormException($e);
                    }
                }),

            Actions\Action::make('back_to_gateways')
                ->label('← Back to Gateways')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->action(function (): void {
                    $this->navigateWithStatePreservation($this->getResource()::getUrl('index'));
                })
                ->extraAttributes([
                    'class' => 'fi-btn-outlined',
                ]),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('device_name')
                    ->label('Device Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('device_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => Device::DEVICE_TYPES[$state] ?? 'Unknown')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'energy_meter' => 'warning',
                        'water_meter' => 'info',
                        'control' => 'success',
                        default => 'gray'
                    }),
                
                Tables\Columns\TextColumn::make('load_category')
                    ->label('Load Category')
                    ->formatStateUsing(fn (string $state): string => Device::LOAD_CATEGORIES[$state] ?? 'Unknown'),
                
                Tables\Columns\TextColumn::make('registers_count')
                    ->label('Registers')
                    ->alignCenter()
                    ->counts('registers'),
                
                Tables\Columns\TextColumn::make('enabled_registers_count')
                    ->label('Active')
                    ->alignCenter()
                    ->color('success')
                    ->getStateUsing(fn (Device $record): int => $record->registers()->where('enabled', true)->count()),
                
                Tables\Columns\IconColumn::make('enabled')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('device_type')
                    ->options(Device::getDeviceTypeOptions()),
                
                Tables\Filters\SelectFilter::make('load_category')
                    ->options(Device::getLoadCategoryOptions()),
                
                Tables\Filters\TernaryFilter::make('enabled')
                    ->label('Status')
                    ->placeholder('All devices')
                    ->trueLabel('Enabled only')
                    ->falseLabel('Disabled only'),
            ])
            ->actions([
                Tables\Actions\Action::make('manage_registers')
                    ->label('Manage Registers')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('primary')
                    ->action(function (Device $record): void {
                        $this->navigateWithStatePreservation(ManageDeviceRegisters::getUrl([
                            'gateway' => $this->record->id,
                            'device' => $record->id
                        ]));
                    }),
                
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\TextInput::make('device_name')
                            ->label('Device Name')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\Select::make('device_type')
                            ->label('Device Type')
                            ->options(Device::getDeviceTypeOptions())
                            ->required(),
                        
                        Forms\Components\Select::make('load_category')
                            ->label('Load Category')
                            ->options(Device::getLoadCategoryOptions())
                            ->required(),
                        
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enabled')
                            ->helperText('Enable this device for data collection'),
                    ])
                    ->using(function (Device $record, array $data): Device {
                        $record->update($data);
                        return $record;
                    }),
                
                Tables\Actions\DeleteAction::make()
                    ->using(function (Device $record): void {
                        // Delete the device - registers will be cascade deleted via foreign key constraint
                        $record->delete();
                    })
                    ->successNotificationTitle('Device and all its registers deleted')
                    ->requiresConfirmation()
                    ->modalHeading('Delete Device')
                    ->modalDescription('Are you sure you want to delete this device? All associated registers will also be deleted.')
                    ->modalSubmitActionLabel('Yes, delete it'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Devices')
                        ->modalDescription('Are you sure you want to delete the selected devices? All associated registers will also be deleted.')
                        ->modalSubmitActionLabel('Yes, delete them'),
                    
                    Tables\Actions\BulkAction::make('enable')
                        ->label('Enable Selected')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                $record->update(['enabled' => true]);
                            }
                            
                            Notification::make()
                                ->title('Devices Enabled')
                                ->body(count($records) . ' devices have been enabled.')
                                ->success()
                                ->send();
                        }),
                    
                    Tables\Actions\BulkAction::make('disable')
                        ->label('Disable Selected')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                $record->update(['enabled' => false]);
                            }
                            
                            Notification::make()
                                ->title('Devices Disabled')
                                ->body(count($records) . ' devices have been disabled.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\Action::make('add_first_device')
                    ->label('Add First Device')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('device_name')
                            ->label('Device Name')
                            ->required()
                            ->placeholder('Living Room Heater')
                            ->maxLength(255),
                        
                        Forms\Components\Select::make('device_type')
                            ->label('Device Type')
                            ->options(Device::getDeviceTypeOptions())
                            ->default('energy_meter')
                            ->required(),
                        
                        Forms\Components\Select::make('load_category')
                            ->label('Load Category')
                            ->options(Device::getLoadCategoryOptions())
                            ->default('other')
                            ->required(),
                        
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enabled')
                            ->default(true)
                            ->helperText('Enable this device for data collection'),
                    ])
                    ->action(function (array $data): void {
                        // Create the device using the Device model
                        $device = Device::create([
                            'gateway_id' => $this->record->id,
                            'device_name' => $data['device_name'],
                            'device_type' => $data['device_type'],
                            'load_category' => $data['load_category'],
                            'enabled' => $data['enabled'] ?? true,
                        ]);

                        // Create a default register for the new device
                        Register::create([
                            'device_id' => $device->id,
                            'technical_label' => $data['device_name'] . '_Default_Register',
                            'function' => 4, // Input Registers
                            'register_address' => 1025,
                            'data_type' => 'float32',
                            'byte_order' => 'word_swap',
                            'scale' => 1.0,
                            'count' => 2,
                            'enabled' => true,
                        ]);

                        Notification::make()
                            ->title('Device Created')
                            ->body("Device '{$data['device_name']}' has been created with a default register.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return app(\App\Services\QueryOptimizationService::class)
            ->optimizeDeviceQuery(
                Device::query()->where('gateway_id', $this->record->id)
            );
    }
}