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

class ManageDeviceRegisters extends Page implements Tables\Contracts\HasTable, Forms\Contracts\HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;
    use PreservesNavigationState;

    protected static string $resource = GatewayResource::class;

    protected static string $view = 'filament.resources.gateway-resource.pages.manage-device-registers';

    public Gateway $gateway;
    public Device $device;

    public function getTitle(): string
    {
        return "Registers";
    }

    public function getHeading(): string
    {
        return app(NavigationContextService::class)->generatePageTitle('registers', $this->gateway, $this->device);
    }

    public function getSubheading(): ?string
    {
        return app(NavigationContextService::class)->generatePageSubheading('registers', $this->gateway, $this->device);
    }

    public function getBreadcrumbs(): array
    {
        return app(NavigationContextService::class)->generateBreadcrumbs('registers', $this->gateway, $this->device);
    }

    protected function getNavigationContext(): array
    {
        return app(NavigationContextService::class)->generateNavigationContext('registers', $this->gateway, $this->device);
    }

    public function mount(int|string $gateway, int|string $device): void
    {
        $this->gateway = Gateway::findOrFail($gateway);
        $this->device = Device::findOrFail($device);
        
        // Verify the device belongs to the gateway
        if ($this->device->gateway_id !== $this->gateway->id) {
            abort(404, 'Device not found for this gateway');
        }
        
        // Restore table state from session if available
        $this->restoreTableState();
    }

    protected function getStateIdentifier(): string
    {
        return "device_{$this->device->id}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add_register')
                ->label('Add Register')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->form([
                    Forms\Components\TextInput::make('technical_label')
                        ->label('Register Name (Technical Label)')
                        ->required()
                        ->placeholder('Total_kWh')
                        ->helperText('Technical identifier for this register')
                        ->maxLength(255),
                    
                    Forms\Components\Select::make('function')
                        ->label('Modbus Function')
                        ->options(Register::FUNCTIONS)
                        ->default(4)
                        ->required(),
                    
                    Forms\Components\TextInput::make('register_address')
                        ->label('Register Address')
                        ->required()
                        ->numeric()
                        ->placeholder('1025')
                        ->helperText('Valid Modbus register address (0-65535)')
                        ->rules([
                            new \App\Rules\ModbusAddressRule(),
                            new \App\Rules\UniqueRegisterAddressInDeviceRule($this->device->id),
                        ])
                        ->validationMessages([
                            'required' => 'Register address is required.',
                            'numeric' => 'Register address must be a number.',
                        ])
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Get $get, $state) {
                            if ($state && $get('count')) {
                                $validationService = app(\App\Services\ValidationService::class);
                                if (!$validationService->validateModbusAddressRange((int) $state, (int) $get('count'))) {
                                    // This will be caught by validation rules
                                }
                            }
                        }),
                    
                    Forms\Components\Select::make('data_type')
                        ->label('Data Type')
                        ->options(Register::DATA_TYPES)
                        ->default('float32')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            $count = match($state) {
                                'int16', 'uint16' => 1,
                                'int32', 'uint32', 'float32' => 2,
                                'float64' => 4,
                                default => 2
                            };
                            $set('count', $count);
                        }),
                    
                    Forms\Components\Select::make('byte_order')
                        ->label('Byte Order')
                        ->options(Register::BYTE_ORDERS)
                        ->default('word_swap')
                        ->required(),
                    
                    Forms\Components\TextInput::make('scale')
                        ->label('Scale Factor')
                        ->numeric()
                        ->default(1.0)
                        ->step(0.000001)
                        ->placeholder('1.0')
                        ->helperText('Scaling factor for raw register values')
                        ->rules([
                            new \App\Rules\ScaleFactorRule(),
                        ])
                        ->validationMessages([
                            'numeric' => 'Scale factor must be a number.',
                        ]),
                    
                    Forms\Components\TextInput::make('count')
                        ->label('Register Count')
                        ->numeric()
                        ->default(2)
                        ->minValue(1)
                        ->maxValue(4)
                        ->helperText('Auto-filled based on data type')
                        ->rules([
                            function (Forms\Get $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $dataType = $get('data_type');
                                    $registerAddress = $get('register_address');
                                    
                                    // Use validation service for consistent validation
                                    $validationService = app(\App\Services\ValidationService::class);
                                    
                                    // Validate register range doesn't exceed Modbus limits
                                    if ($registerAddress && $value) {
                                        if (!$validationService->validateModbusAddressRange((int) $registerAddress, (int) $value)) {
                                            $fail('Register address range exceeds Modbus limit (65535).');
                                        }
                                    }
                                    
                                    // Validate count matches data type requirements
                                    if ($dataType && $value) {
                                        $rule = new \App\Rules\RegisterCountForDataTypeRule($dataType);
                                        $rule->validate($attribute, $value, $fail);
                                    }
                                };
                            },
                        ])
                        ->live()
                        ->afterStateUpdated(function (Forms\Get $get, $state) {
                            // Real-time validation feedback
                            $registerAddress = $get('register_address');
                            if ($registerAddress && $state) {
                                $validationService = app(\App\Services\ValidationService::class);
                                if (!$validationService->validateModbusAddressRange((int) $registerAddress, (int) $state)) {
                                    // This will be caught by the validation rule above
                                }
                            }
                        }),
                    
                    Forms\Components\Toggle::make('enabled')
                        ->label('Enabled')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    try {
                        // Validate register data using the validation service
                        $validationErrors = Register::validateData([
                            'device_id' => $this->device->id,
                            'technical_label' => $data['technical_label'],
                            'function' => $data['function'],
                            'register_address' => $data['register_address'],
                            'data_type' => $data['data_type'],
                            'byte_order' => $data['byte_order'],
                            'scale' => $data['scale'] ?? 1.0,
                            'count' => $data['count'],
                            'enabled' => $data['enabled'] ?? true,
                        ], $this->device->id);

                        if (!empty($validationErrors)) {
                            $firstError = collect($validationErrors)->flatten()->first();
                            Notification::make()
                                ->title('Validation Error')
                                ->body($firstError)
                                ->danger()
                                ->send();
                            return;
                        }

                        $register = Register::create([
                            'device_id' => $this->device->id,
                            'technical_label' => $data['technical_label'],
                            'function' => $data['function'],
                            'register_address' => $data['register_address'],
                            'data_type' => $data['data_type'],
                            'byte_order' => $data['byte_order'],
                            'scale' => $data['scale'] ?? 1.0,
                            'count' => $data['count'],
                            'enabled' => $data['enabled'] ?? true,
                        ]);

                        // Validate the created register configuration
                        $configErrors = $register->validateAllConstraints();
                        
                        if (!empty($configErrors)) {
                            app(\App\Services\FormExceptionHandlerService::class)->showSuccessWithWarnings(
                                'Register Added',
                                "Register '{$data['technical_label']}' has been added to the device.",
                                $configErrors
                            );
                        } else {
                            Notification::make()
                                ->title('Register Added')
                                ->body("Register '{$data['technical_label']}' has been added to the device.")
                                ->success()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        app(\App\Services\FormExceptionHandlerService::class)->handleRegisterFormException($e);
                    }
                }),

            Actions\Action::make('enable_all')
                ->label('Enable All')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(function (): void {
                    try {
                        $updated = $this->device->registers()->update(['enabled' => true]);

                        Notification::make()
                            ->title('All Registers Enabled')
                            ->body("Enabled {$updated} registers.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        app(\App\Services\FormExceptionHandlerService::class)->handleBulkOperationException($e, 'enable all registers');
                    }
                }),

            Actions\Action::make('disable_all')
                ->label('Disable All')
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->action(function (): void {
                    try {
                        $updated = $this->device->registers()->update(['enabled' => false]);

                        Notification::make()
                            ->title('All Registers Disabled')
                            ->body("Disabled {$updated} registers.")
                            ->warning()
                            ->send();
                    } catch (\Exception $e) {
                        app(\App\Services\FormExceptionHandlerService::class)->handleBulkOperationException($e, 'disable all registers');
                    }
                }),

            Actions\Action::make('validate_all')
                ->label('Validate All')
                ->icon('heroicon-o-shield-check')
                ->color('info')
                ->action(function (): void {
                    $registers = $this->device->registers;
                    $validCount = 0;
                    $invalidCount = 0;
                    $errors = [];
                    
                    foreach ($registers as $register) {
                        if ($register->isValid()) {
                            $validCount++;
                        } else {
                            $invalidCount++;
                            $errors[] = "{$register->technical_label}: {$register->validation_errors}";
                        }
                    }
                    
                    $message = "Validation complete: {$validCount} valid, {$invalidCount} invalid registers.";
                    if (!empty($errors)) {
                        $message .= "\n\nErrors:\n" . implode("\n", array_slice($errors, 0, 5));
                        if (count($errors) > 5) {
                            $message .= "\n... and " . (count($errors) - 5) . " more errors.";
                        }
                    }
                    
                    Notification::make()
                        ->title('Device Register Validation')
                        ->body($message)
                        ->color($invalidCount > 0 ? 'warning' : 'success')
                        ->send();
                }),

            Actions\Action::make('back_to_devices')
                ->label('← Back to Devices')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->action(function (): void {
                    $this->navigateWithStatePreservation(ManageGatewayDevices::getUrl(['record' => $this->gateway->id]));
                })
                ->extraAttributes([
                    'class' => 'fi-btn-outlined',
                ]),

            Actions\Action::make('back_to_gateways')
                ->label('← Back to Gateways')
                ->icon('heroicon-o-home')
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
                Tables\Columns\TextColumn::make('technical_label')
                    ->label('Register Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('function')
                    ->label('Function')
                    ->formatStateUsing(fn (int $state): string => Register::FUNCTIONS[$state] ?? (string) $state)
                    ->badge()
                    ->color(fn (int $state): string => match($state) {
                        1 => 'success', // Coils
                        2 => 'info',    // Discrete Inputs
                        3 => 'warning', // Holding Registers
                        4 => 'primary', // Input Registers
                        default => 'gray'
                    }),
                
                Tables\Columns\TextColumn::make('register_address')
                    ->label('Address')
                    ->sortable()
                    ->formatStateUsing(function (Register $record): string {
                        $endAddress = $record->register_address + $record->count - 1;
                        return $record->count > 1 
                            ? "{$record->register_address}-{$endAddress}" 
                            : (string) $record->register_address;
                    })
                    ->description(fn (Register $record): string => "Count: {$record->count}"),
                
                Tables\Columns\TextColumn::make('data_type')
                    ->label('Data Type')
                    ->formatStateUsing(fn (string $state): string => Register::DATA_TYPES[$state] ?? strtoupper($state)),
                
                Tables\Columns\TextColumn::make('byte_order')
                    ->label('Byte Order')
                    ->formatStateUsing(fn (string $state): string => Register::BYTE_ORDERS[$state] ?? $state),
                
                Tables\Columns\TextColumn::make('scale')
                    ->label('Scale')
                    ->numeric(decimalPlaces: 6),
                
                Tables\Columns\TextColumn::make('count')
                    ->label('Count')
                    ->alignCenter(),
                
                Tables\Columns\IconColumn::make('enabled')
                    ->label('Enabled')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                Tables\Columns\IconColumn::make('validation_status')
                    ->label('Valid')
                    ->getStateUsing(fn (Register $record): bool => $record->isValid())
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (Register $record): string => 
                        $record->isValid() ? 'Configuration is valid' : $record->validation_errors
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('function')
                    ->label('Function')
                    ->options(Register::FUNCTIONS),
                
                Tables\Filters\SelectFilter::make('data_type')
                    ->label('Data Type')
                    ->options(Register::DATA_TYPES),
                
                Tables\Filters\TernaryFilter::make('enabled')
                    ->label('Status')
                    ->placeholder('All registers')
                    ->trueLabel('Enabled only')
                    ->falseLabel('Disabled only'),
                
                Tables\Filters\Filter::make('validation_status')
                    ->label('Validation Status')
                    ->form([
                        Forms\Components\Select::make('validation')
                            ->label('Show')
                            ->options([
                                'valid' => 'Valid registers only',
                                'invalid' => 'Invalid registers only',
                            ])
                            ->placeholder('All registers'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['validation'])) {
                            return $query;
                        }
                        
                        return $query->where(function ($q) use ($data) {
                            $registers = Register::where('device_id', $this->device->id)->get();
                            $validIds = [];
                            $invalidIds = [];
                            
                            foreach ($registers as $register) {
                                if ($register->isValid()) {
                                    $validIds[] = $register->id;
                                } else {
                                    $invalidIds[] = $register->id;
                                }
                            }
                            
                            if ($data['validation'] === 'valid' && !empty($validIds)) {
                                $q->whereIn('id', $validIds);
                            } elseif ($data['validation'] === 'invalid' && !empty($invalidIds)) {
                                $q->whereIn('id', $invalidIds);
                            } else {
                                // If no matching records, return empty result
                                $q->whereRaw('1 = 0');
                            }
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\TextInput::make('technical_label')
                            ->label('Register Name')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\Select::make('function')
                            ->label('Modbus Function')
                            ->options(Register::FUNCTIONS)
                            ->required(),
                        
                        Forms\Components\TextInput::make('register_address')
                            ->label('Register Address')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(65535)
                            ->helperText('Valid Modbus register address (0-65535)')
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        if (!is_numeric($value) || $value < 0 || $value > 65535) {
                                            $fail('Register address must be between 0 and 65535.');
                                        }
                                    };
                                },
                            ]),
                        
                        Forms\Components\Select::make('data_type')
                            ->label('Data Type')
                            ->options(Register::DATA_TYPES)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                $count = match($state) {
                                    'int16', 'uint16' => 1,
                                    'int32', 'uint32', 'float32' => 2,
                                    'float64' => 4,
                                    default => 2
                                };
                                $set('count', $count);
                            }),
                        
                        Forms\Components\Select::make('byte_order')
                            ->label('Byte Order')
                            ->options(Register::BYTE_ORDERS)
                            ->required(),
                        
                        Forms\Components\TextInput::make('scale')
                            ->label('Scale Factor')
                            ->numeric()
                            ->step(0.000001)
                            ->helperText('Scaling factor for raw register values')
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        if ($value !== null && ($value <= 0 || $value > 1000000)) {
                                            $fail('Scale factor must be between 0 and 1,000,000.');
                                        }
                                    };
                                },
                            ]),
                        
                        Forms\Components\TextInput::make('count')
                            ->label('Register Count')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(4)
                            ->helperText('Number of consecutive registers to read')
                            ->rules([
                                function (Forms\Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $dataType = $get('data_type');
                                        $registerAddress = $get('register_address');
                                        
                                        // Validate register range doesn't exceed Modbus limits
                                        if ($registerAddress && $value) {
                                            $endAddress = $registerAddress + $value - 1;
                                            if ($endAddress > 65535) {
                                                $fail('Register address range exceeds Modbus limit (65535).');
                                            }
                                        }
                                        
                                        // Validate count matches data type requirements
                                        $requiredCount = match($dataType) {
                                            'int16', 'uint16' => 1,
                                            'int32', 'uint32', 'float32' => 2,
                                            'float64' => 4,
                                            default => 2
                                        };
                                        
                                        if ($value < $requiredCount) {
                                            $fail("Register count must be at least {$requiredCount} for {$dataType} data type.");
                                        }
                                    };
                                },
                            ]),
                        
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enabled'),
                    ]),
                
                Tables\Actions\Action::make('toggle_enabled')
                    ->label(fn (Register $record): string => $record->enabled ? 'Disable' : 'Enable')
                    ->icon(fn (Register $record): string => $record->enabled ? 'heroicon-o-x-mark' : 'heroicon-o-check')
                    ->color(fn (Register $record): string => $record->enabled ? 'warning' : 'success')
                    ->action(function (Register $record): void {
                        $record->update(['enabled' => !$record->enabled]);
                        
                        $status = $record->enabled ? 'enabled' : 'disabled';
                        Notification::make()
                            ->title('Register Updated')
                            ->body("Register '{$record->technical_label}' has been {$status}.")
                            ->success()
                            ->send();
                    }),
                
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('enable_selected')
                        ->label('Enable Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records): void {
                            $count = 0;
                            foreach ($records as $record) {
                                $record->update(['enabled' => true]);
                                $count++;
                            }
                            
                            Notification::make()
                                ->title('Registers Enabled')
                                ->body("Enabled {$count} registers.")
                                ->success()
                                ->send();
                        }),
                    
                    Tables\Actions\BulkAction::make('disable_selected')
                        ->label('Disable Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->action(function ($records): void {
                            $count = 0;
                            foreach ($records as $record) {
                                $record->update(['enabled' => false]);
                                $count++;
                            }
                            
                            Notification::make()
                                ->title('Registers Disabled')
                                ->body("Disabled {$count} registers.")
                                ->warning()
                                ->send();
                        }),
                    
                    Tables\Actions\BulkAction::make('validate_selected')
                        ->label('Validate Selected')
                        ->icon('heroicon-o-shield-check')
                        ->color('info')
                        ->action(function ($records): void {
                            $validCount = 0;
                            $invalidCount = 0;
                            $errors = [];
                            
                            foreach ($records as $record) {
                                if ($record->isValid()) {
                                    $validCount++;
                                } else {
                                    $invalidCount++;
                                    $errors[] = "{$record->technical_label}: {$record->validation_errors}";
                                }
                            }
                            
                            $message = "Validation complete: {$validCount} valid, {$invalidCount} invalid registers.";
                            if (!empty($errors)) {
                                $message .= "\n\nErrors:\n" . implode("\n", array_slice($errors, 0, 5));
                                if (count($errors) > 5) {
                                    $message .= "\n... and " . (count($errors) - 5) . " more errors.";
                                }
                            }
                            
                            Notification::make()
                                ->title('Validation Results')
                                ->body($message)
                                ->color($invalidCount > 0 ? 'warning' : 'success')
                                ->send();
                        }),
                    
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\Action::make('add_first_register')
                    ->label('Add First Register')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('technical_label')
                            ->label('Register Name')
                            ->required()
                            ->placeholder('Total_kWh')
                            ->maxLength(255),
                        
                        Forms\Components\Select::make('function')
                            ->label('Modbus Function')
                            ->options(Register::FUNCTIONS)
                            ->default(4)
                            ->required(),
                        
                        Forms\Components\TextInput::make('register_address')
                            ->label('Register Address')
                            ->required()
                            ->numeric()
                            ->default(1025)
                            ->minValue(0)
                            ->maxValue(65535),
                    ])
                    ->action(function (array $data): void {
                        // Check for duplicate register addresses
                        $existingRegister = Register::where('device_id', $this->device->id)
                            ->where('register_address', $data['register_address'])
                            ->first();
                        
                        if ($existingRegister) {
                            Notification::make()
                                ->title('Validation Error')
                                ->body("A register with address {$data['register_address']} already exists for this device.")
                                ->danger()
                                ->send();
                            return;
                        }

                        Register::create([
                            'device_id' => $this->device->id,
                            'technical_label' => $data['technical_label'],
                            'function' => $data['function'],
                            'register_address' => $data['register_address'],
                            'data_type' => 'float32',
                            'byte_order' => 'word_swap',
                            'scale' => 1.0,
                            'count' => 2,
                            'enabled' => true,
                        ]);

                        Notification::make()
                            ->title('Register Created')
                            ->body("Register '{$data['technical_label']}' has been created.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return app(\App\Services\QueryOptimizationService::class)
            ->optimizeRegisterQuery(
                Register::query()->where('device_id', $this->device->id)
            );
    }
}