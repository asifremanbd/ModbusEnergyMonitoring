<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceResource\Pages;
use App\Models\Device;
use App\Models\Gateway;
use App\Models\Register;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'Devices';

    protected static ?string $modelLabel = 'Device';

    protected static ?string $pluralModelLabel = 'Devices';

    protected static ?int $navigationSort = 2;
    
    // Hide from navigation
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Device Information')
                    ->schema([
                        Forms\Components\Select::make('gateway_id')
                            ->label('Gateway')
                            ->options(Gateway::all()->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                // Clear device name when gateway changes to trigger validation
                                $set('device_name', '');
                            }),
                        
                        Forms\Components\TextInput::make('device_name')
                            ->label('Device Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Living Room Heater')
                            ->rules([
                                function (Forms\Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $gatewayId = $get('gateway_id');
                                        if ($gatewayId && $value) {
                                            $validationService = app(\App\Services\ValidationService::class);
                                            if (!$validationService->isDeviceNameUniqueInGateway($value, $gatewayId)) {
                                                $fail('A device with this name already exists in the selected gateway.');
                                            }
                                        }
                                    };
                                },
                            ]),
                        
                        Forms\Components\Select::make('device_type')
                            ->label('Device Type')
                            ->options(Device::DEVICE_TYPES)
                            ->default('energy_meter')
                            ->required(),
                        
                        Forms\Components\Select::make('load_category')
                            ->label('Load Category')
                            ->options(Device::LOAD_CATEGORIES)
                            ->default('other')
                            ->required(),
                        
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enabled')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Device::with(['gateway', 'registers']))
            ->columns([
                Tables\Columns\TextColumn::make('gateway.name')
                    ->label('Gateway')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Device $record): string => 
                        $record->gateway ? "{$record->gateway->ip_address}:{$record->gateway->port}" : ''
                    )
                    ->tooltip(fn (Device $record): string => 
                        $record->gateway ? "Gateway: {$record->gateway->name} ({$record->gateway->ip_address}:{$record->gateway->port})" : ''
                    ),
                
                Tables\Columns\TextColumn::make('device_name')
                    ->label('Device Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Device $record): string => 
                        "ID: {$record->id}"
                    ),
                
                Tables\Columns\TextColumn::make('device_type_name')
                    ->label('Type')
                    ->badge()
                    ->searchable(['device_type'])
                    ->sortable(['device_type'])
                    ->color(fn (string $state): string => match ($state) {
                        'Energy Meter' => 'success',
                        'Water Meter' => 'info',
                        'Control Device' => 'warning',
                        'Other' => 'gray',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('load_category_name')
                    ->label('Category')
                    ->searchable(['load_category'])
                    ->sortable(['load_category'])
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'HVAC' => 'info',
                        'Lighting' => 'warning',
                        'Sockets' => 'success',
                        'Other' => 'gray',
                        default => 'primary',
                    }),
                
                Tables\Columns\TextColumn::make('registers_count')
                    ->label('Total Registers')
                    ->alignCenter()
                    ->badge()
                    ->color('primary')
                    ->counts('registers')
                    ->tooltip('Total number of registers configured for this device'),
                
                Tables\Columns\TextColumn::make('enabled_registers_count')
                    ->label('Active Registers')
                    ->alignCenter()
                    ->badge()
                    ->getStateUsing(fn (Device $record): int => $record->enabled_registers_count)
                    ->color(fn (int $state, Device $record): string => 
                        $state === $record->registers_count ? 'success' : 
                        ($state > 0 ? 'warning' : 'danger')
                    )
                    ->tooltip(fn (Device $record): string => 
                        "Active: {$record->enabled_registers_count} / Total: {$record->registers_count}"
                    ),
                
                Tables\Columns\IconColumn::make('enabled')
                    ->label('Device Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn (Device $record): string => 
                        $record->enabled ? 'Device is enabled' : 'Device is disabled'
                    ),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gateway_id')
                    ->label('Gateway')
                    ->options(fn (): array => Gateway::orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->placeholder('All gateways'),
                
                Tables\Filters\SelectFilter::make('device_type')
                    ->label('Device Type')
                    ->options(Device::DEVICE_TYPES)
                    ->multiple()
                    ->placeholder('All types'),
                
                Tables\Filters\SelectFilter::make('load_category')
                    ->label('Load Category')
                    ->options(Device::LOAD_CATEGORIES)
                    ->multiple()
                    ->placeholder('All categories'),
                
                Tables\Filters\TernaryFilter::make('enabled')
                    ->label('Device Status')
                    ->placeholder('All devices')
                    ->trueLabel('Enabled only')
                    ->falseLabel('Disabled only'),
                
                Tables\Filters\Filter::make('register_statistics')
                    ->label('Register Statistics')
                    ->form([
                        Forms\Components\Select::make('register_status')
                            ->label('Register Status')
                            ->options([
                                'with_registers' => 'Has registers',
                                'without_registers' => 'No registers',
                                'with_enabled_registers' => 'Has active registers',
                                'with_disabled_registers' => 'Has only disabled registers',
                                'fully_configured' => 'All registers enabled',
                                'partially_configured' => 'Some registers disabled',
                            ])
                            ->placeholder('All devices'),
                        
                        Forms\Components\TextInput::make('min_registers')
                            ->label('Minimum Registers')
                            ->numeric()
                            ->placeholder('0'),
                        
                        Forms\Components\TextInput::make('max_registers')
                            ->label('Maximum Registers')
                            ->numeric()
                            ->placeholder('No limit'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (isset($data['register_status'])) {
                            $query = match($data['register_status']) {
                                'with_registers' => $query->has('registers'),
                                'without_registers' => $query->doesntHave('registers'),
                                'with_enabled_registers' => $query->whereHas('registers', fn ($q) => $q->where('enabled', true)),
                                'with_disabled_registers' => $query->whereHas('registers', fn ($q) => $q->where('enabled', false))
                                    ->whereDoesntHave('registers', fn ($q) => $q->where('enabled', true)),
                                'fully_configured' => $query->whereHas('registers')
                                    ->whereDoesntHave('registers', fn ($q) => $q->where('enabled', false)),
                                'partially_configured' => $query->whereHas('registers', fn ($q) => $q->where('enabled', true))
                                    ->whereHas('registers', fn ($q) => $q->where('enabled', false)),
                                default => $query,
                            };
                        }
                        
                        if (isset($data['min_registers']) && is_numeric($data['min_registers'])) {
                            $query->withCount('registers')->having('registers_count', '>=', (int) $data['min_registers']);
                        }
                        
                        if (isset($data['max_registers']) && is_numeric($data['max_registers'])) {
                            $query->withCount('registers')->having('registers_count', '<=', (int) $data['max_registers']);
                        }
                        
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('manage_registers')
                        ->label('Manage Registers')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->color('primary')
                        ->url(fn (Device $record): string => 
                            DeviceResource::getUrl('manage-registers', ['device' => $record->id])
                        )
                        ->tooltip('Configure Modbus registers for this device'),
                    
                    Tables\Actions\Action::make('quick_add_register')
                        ->label('Quick Add Register')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->visible(fn (Device $record): bool => $record->registers_count < 10)
                        ->form([
                            Forms\Components\TextInput::make('technical_label')
                                ->label('Register Name')
                                ->required()
                                ->placeholder('Total_kWh')
                                ->maxLength(255),
                            
                            Forms\Components\Select::make('function')
                                ->label('Modbus Function')
                                ->options(\App\Models\Register::FUNCTIONS)
                                ->default(4)
                                ->required(),
                            
                            Forms\Components\TextInput::make('register_address')
                                ->label('Register Address')
                                ->required()
                                ->numeric()
                                ->placeholder('1025'),
                            
                            Forms\Components\Select::make('data_type')
                                ->label('Data Type')
                                ->options(\App\Models\Register::DATA_TYPES)
                                ->default('float32')
                                ->required(),
                        ])
                        ->action(function (array $data, Device $record): void {
                            try {
                                $count = match($data['data_type']) {
                                    'int16', 'uint16' => 1,
                                    'int32', 'uint32', 'float32' => 2,
                                    'float64' => 4,
                                    default => 2
                                };
                                
                                \App\Models\Register::create([
                                    'device_id' => $record->id,
                                    'technical_label' => $data['technical_label'],
                                    'function' => $data['function'],
                                    'register_address' => $data['register_address'],
                                    'data_type' => $data['data_type'],
                                    'byte_order' => 'word_swap',
                                    'scale' => 1.0,
                                    'count' => $count,
                                    'enabled' => true,
                                ]);
                                
                                Notification::make()
                                    ->title('Register Added')
                                    ->body("Register '{$data['technical_label']}' has been added.")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error Adding Register')
                                    ->body('Failed to add register. Please check the configuration.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->tooltip('Quickly add a register with default settings'),
                ])
                ->label('Registers')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('primary'),
                
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->slideOver()
                        ->tooltip('Edit device configuration'),
                    
                    Tables\Actions\Action::make('duplicate')
                        ->label('Duplicate Device')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->form([
                            Forms\Components\TextInput::make('device_name')
                                ->label('New Device Name')
                                ->required()
                                ->placeholder('Copy of Original Device'),
                            
                            Forms\Components\Toggle::make('copy_registers')
                                ->label('Copy Registers')
                                ->default(true)
                                ->helperText('Copy all registers from the original device'),
                        ])
                        ->action(function (array $data, Device $record): void {
                            try {
                                $newDevice = Device::create([
                                    'gateway_id' => $record->gateway_id,
                                    'device_name' => $data['device_name'],
                                    'device_type' => $record->device_type,
                                    'load_category' => $record->load_category,
                                    'enabled' => $record->enabled,
                                ]);
                                
                                if ($data['copy_registers'] && $record->registers_count > 0) {
                                    foreach ($record->registers as $register) {
                                        \App\Models\Register::create([
                                            'device_id' => $newDevice->id,
                                            'technical_label' => $register->technical_label,
                                            'function' => $register->function,
                                            'register_address' => $register->register_address,
                                            'data_type' => $register->data_type,
                                            'byte_order' => $register->byte_order,
                                            'scale' => $register->scale,
                                            'count' => $register->count,
                                            'enabled' => $register->enabled,
                                        ]);
                                    }
                                }
                                
                                Notification::make()
                                    ->title('Device Duplicated')
                                    ->body("Device '{$data['device_name']}' has been created" . 
                                           ($data['copy_registers'] ? " with {$record->registers_count} registers." : "."))
                                    ->success()
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('edit_new')
                                            ->label('Edit New Device')
                                            ->url(DeviceResource::getUrl('edit', ['record' => $newDevice->id]))
                                            ->button(),
                                    ])
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error Duplicating Device')
                                    ->body('Failed to duplicate device. Please try again.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->tooltip('Create a copy of this device'),
                ])
                ->label('Device')
                ->icon('heroicon-o-cpu-chip')
                ->color('gray'),
                
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view_gateway')
                        ->label('View Gateway')
                        ->icon('heroicon-o-server')
                        ->color('info')
                        ->url(fn (Device $record): string => 
                            \App\Filament\Resources\GatewayResource::getUrl('edit', ['record' => $record->gateway_id])
                        )
                        ->openUrlInNewTab()
                        ->tooltip('View the gateway this device belongs to'),
                    
                    Tables\Actions\Action::make('view_gateway_devices')
                        ->label('View All Gateway Devices')
                        ->icon('heroicon-o-queue-list')
                        ->color('info')
                        ->url(fn (Device $record): string => 
                            \App\Filament\Resources\GatewayResource::getUrl('manage-devices', ['record' => $record->gateway_id])
                        )
                        ->tooltip('View all devices for this gateway'),
                ])
                ->label('Gateway')
                ->icon('heroicon-o-server')
                ->color('info'),
                
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Device')
                    ->modalDescription(fn (Device $record): string => 
                        "This will permanently delete the device '{$record->device_name}' and all its {$record->registers_count} registers. This action cannot be undone."
                    )
                    ->modalSubmitActionLabel('Delete Device')
                    ->color('danger'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('enable_selected')
                        ->label('Enable Selected Devices')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $updated = $records->each->update(['enabled' => true]);
                            
                            Notification::make()
                                ->title('Devices Enabled')
                                ->body(count($records) . ' devices have been enabled.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    
                    Tables\Actions\BulkAction::make('disable_selected')
                        ->label('Disable Selected Devices')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $updated = $records->each->update(['enabled' => false]);
                            
                            Notification::make()
                                ->title('Devices Disabled')
                                ->body(count($records) . ' devices have been disabled.')
                                ->warning()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    
                    Tables\Actions\BulkAction::make('enable_all_registers')
                        ->label('Enable All Registers')
                        ->icon('heroicon-o-bolt')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $totalUpdated = 0;
                            foreach ($records as $device) {
                                $updated = $device->registers()->update(['enabled' => true]);
                                $totalUpdated += $updated;
                            }
                            
                            Notification::make()
                                ->title('Registers Enabled')
                                ->body("Enabled {$totalUpdated} registers across " . count($records) . " devices.")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Enable All Registers')
                        ->modalDescription('This will enable all registers for the selected devices.')
                        ->deselectRecordsAfterCompletion(),
                    
                    Tables\Actions\BulkAction::make('disable_all_registers')
                        ->label('Disable All Registers')
                        ->icon('heroicon-o-bolt-slash')
                        ->color('warning')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $totalUpdated = 0;
                            foreach ($records as $device) {
                                $updated = $device->registers()->update(['enabled' => false]);
                                $totalUpdated += $updated;
                            }
                            
                            Notification::make()
                                ->title('Registers Disabled')
                                ->body("Disabled {$totalUpdated} registers across " . count($records) . " devices.")
                                ->warning()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Disable All Registers')
                        ->modalDescription('This will disable all registers for the selected devices.')
                        ->deselectRecordsAfterCompletion(),
                    
                    Tables\Actions\BulkAction::make('change_gateway')
                        ->label('Move to Gateway')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('info')
                        ->form([
                            Forms\Components\Select::make('gateway_id')
                                ->label('Target Gateway')
                                ->options(fn (): array => Gateway::orderBy('name')->pluck('name', 'id')->toArray())
                                ->required()
                                ->searchable()
                                ->preload(),
                        ])
                        ->action(function (array $data, \Illuminate\Database\Eloquent\Collection $records): void {
                            try {
                                $gateway = Gateway::find($data['gateway_id']);
                                $updated = $records->each->update(['gateway_id' => $data['gateway_id']]);
                                
                                Notification::make()
                                    ->title('Devices Moved')
                                    ->body(count($records) . " devices have been moved to gateway '{$gateway->name}'.")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error Moving Devices')
                                    ->body('Failed to move devices. Please try again.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Move Devices to Gateway')
                        ->modalDescription('This will move the selected devices to a different gateway.')
                        ->deselectRecordsAfterCompletion(),
                    
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Devices')
                        ->modalDescription(fn (\Illuminate\Database\Eloquent\Collection $records): string => 
                            'This will permanently delete ' . count($records) . ' devices and all their registers. This action cannot be undone.'
                        )
                        ->modalSubmitActionLabel('Delete Devices'),
                ]),
            ])
            ->defaultSort('device_name')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->emptyStateHeading('No devices found')
            ->emptyStateDescription('Create your first device to start monitoring Modbus registers. Devices organize your Modbus registers by logical groupings.')
            ->emptyStateIcon('heroicon-o-cpu-chip')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create First Device')
                    ->icon('heroicon-o-plus'),
            ])
            ->recordUrl(fn (Device $record): string => 
                DeviceResource::getUrl('manage-registers', ['device' => $record->id])
            )
            ->recordAction(null); // Disable default edit action on row click
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
            'manage-registers' => Pages\ManageDeviceRegisters::route('/{device}/registers'),
        ];
    }
}