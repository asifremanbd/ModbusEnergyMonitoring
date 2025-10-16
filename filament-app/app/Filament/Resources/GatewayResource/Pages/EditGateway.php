<?php

namespace App\Filament\Resources\GatewayResource\Pages;

use App\Filament\Resources\GatewayResource;
use App\Services\GatewayManagementService;
use App\Services\ModbusPollService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditGateway extends EditRecord
{
    protected static string $resource = GatewayResource::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Gateway Configuration')
                    ->description('Essential gateway settings')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter unique gateway name')
                            ->helperText('Descriptive name to identify this gateway'),
                        
                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->required()
                            ->ip()
                            ->placeholder('192.168.1.100')
                            ->helperText('Static or public IP of the Teltonika gateway'),
                        
                        Forms\Components\TextInput::make('port')
                            ->label('Port')
                            ->required()
                            ->numeric()
                            ->default(502)
                            ->minValue(1)
                            ->maxValue(65535)
                            ->placeholder('502')
                            ->helperText('Modbus TCP port (default: 502)'),
                        
                        Forms\Components\TextInput::make('unit_id')
                            ->label('Unit ID')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(255)
                            ->placeholder('1')
                            ->helperText('Modbus slave/unit identifier (default: 1)'),
                        
                        Forms\Components\TextInput::make('poll_interval')
                            ->label('Poll Interval (seconds)')
                            ->required()
                            ->numeric()
                            ->default(120)
                            ->minValue(10)
                            ->maxValue(3600)
                            ->placeholder('120')
                            ->helperText('Poll frequency (e.g. 120 = every 2 minutes)'),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Enable polling for this gateway (default ON)'),
                    ])
                    ->columns(2)
                    ->compact(),
                
                Forms\Components\Section::make('Data Points')
                    ->description('Configure data points for this gateway')
                    ->schema([
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('enable_all_points')
                                ->label('Enable All')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->action(function (Forms\Set $set, Forms\Get $get) {
                                    $dataPoints = $get('dataPoints') ?? [];
                                    foreach ($dataPoints as $index => $point) {
                                        $set("dataPoints.{$index}.is_enabled", true);
                                    }
                                    
                                    Notification::make()
                                        ->title('All Points Enabled')
                                        ->body('All data points have been enabled.')
                                        ->success()
                                        ->send();
                                })
                                ->visible(fn (Forms\Get $get) => !empty($get('dataPoints'))),
                            
                            Forms\Components\Actions\Action::make('disable_all_points')
                                ->label('Disable All')
                                ->icon('heroicon-o-x-circle')
                                ->color('warning')
                                ->action(function (Forms\Set $set, Forms\Get $get) {
                                    $dataPoints = $get('dataPoints') ?? [];
                                    foreach ($dataPoints as $index => $point) {
                                        $set("dataPoints.{$index}.is_enabled", false);
                                    }
                                    
                                    Notification::make()
                                        ->title('All Points Disabled')
                                        ->body('All data points have been disabled.')
                                        ->warning()
                                        ->send();
                                })
                                ->visible(fn (Forms\Get $get) => !empty($get('dataPoints'))),
                        ])
                        ->visible(fn (Forms\Get $get) => !empty($get('dataPoints'))),
                        
                        Forms\Components\Repeater::make('dataPoints')
                            ->relationship('dataPoints')
                            ->schema([
                                Forms\Components\Grid::make(12)
                                    ->schema([
                                        Forms\Components\Select::make('application')
                                            ->label('Application')
                                            ->options([
                                                'monitoring' => 'Monitoring',
                                                'automation' => 'Automation',
                                            ])
                                            ->default('monitoring')
                                            ->required()
                                            ->reactive()
                                            ->columnSpan(2),
                                        
                                        Forms\Components\Select::make('unit')
                                            ->label('Unit')
                                            ->options([
                                                'kWh' => 'kWh',
                                                'm³' => 'm³',
                                                'none' => 'None',
                                            ])
                                            ->default('kWh')
                                            ->visible(fn (Forms\Get $get): bool => $get('application') === 'monitoring')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\Select::make('load_type')
                                            ->label('Load Type')
                                            ->options([
                                                'power' => 'Power',
                                                'water' => 'Water',
                                                'socket' => 'Socket',
                                                'radiator' => 'Radiator',
                                                'fan' => 'Fan',
                                                'faucet' => 'Faucet',
                                                'ac' => 'AC',
                                                'other' => 'Other',
                                            ])
                                            ->columnSpan(1),
                                        
                                        Forms\Components\TextInput::make('label')
                                            ->label('Custom Label')
                                            ->required()
                                            ->placeholder('Voltage L1')
                                            ->columnSpan(2),
                                        
                                        Forms\Components\Select::make('modbus_function')
                                            ->label('Function')
                                            ->options([
                                                3 => '3 (Holding)',
                                                4 => '4 (Input)',
                                            ])
                                            ->default(4)
                                            ->required()
                                            ->columnSpan(1),
                                        
                                        Forms\Components\TextInput::make('register_address')
                                            ->label('Register')
                                            ->required()
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(65535)
                                            ->placeholder('1')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\TextInput::make('register_count')
                                            ->label('Count')
                                            ->numeric()
                                            ->default(2)
                                            ->minValue(1)
                                            ->maxValue(4)
                                            ->placeholder('2')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\Select::make('data_type')
                                            ->label('Data Type')
                                            ->options([
                                                'int16' => 'Int16',
                                                'uint16' => 'UInt16',
                                                'int32' => 'Int32',
                                                'uint32' => 'UInt32',
                                                'float32' => 'Float32',
                                                'float64' => 'Float64',
                                            ])
                                            ->default('float32')
                                            ->required()
                                            ->columnSpan(1),
                                        
                                        Forms\Components\Select::make('byte_order')
                                            ->label('Byte Order')
                                            ->options([
                                                'big_endian' => 'Big Endian',
                                                'little_endian' => 'Little Endian',
                                                'word_swapped' => 'Word Swapped',
                                            ])
                                            ->default('word_swapped')
                                            ->required()
                                            ->columnSpan(1),
                                        
                                        Forms\Components\TextInput::make('scale_factor')
                                            ->label('Scale')
                                            ->numeric()
                                            ->default(1.0)
                                            ->step(0.000001)
                                            ->placeholder('1.0')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\Toggle::make('is_enabled')
                                            ->label('Enabled')
                                            ->default(true)
                                            ->columnSpan(1),
                                    ])
                            ])
                            ->addActionLabel('Add Data Point')
                            ->reorderable(false)
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->collapsible()
                            ->cloneable()
                            ->defaultItems(0)
                            ->extraItemActions([
                                Forms\Components\Actions\Action::make('preview')
                                    ->label('Preview')
                                    ->icon('heroicon-o-eye')
                                    ->color('info')
                                    ->action(function (array $arguments, Forms\Get $get, array $state) {
                                        $itemData = $arguments['item'];
                                        
                                        // Get gateway data from the form
                                        $ipAddress = $get('ip_address');
                                        $port = $get('port');
                                        $unitId = $get('unit_id');
                                        
                                        // Validate gateway connection data
                                        $errors = [];
                                        
                                        if (empty($ipAddress) || !filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                                            $errors[] = 'Valid IP address is required';
                                        }
                                        
                                        if (empty($port) || !is_numeric($port) || $port < 1 || $port > 65535) {
                                            $errors[] = 'Valid port is required';
                                        }
                                        
                                        if (empty($unitId) || !is_numeric($unitId) || $unitId < 1 || $unitId > 255) {
                                            $errors[] = 'Valid unit ID is required';
                                        }
                                        
                                        if (!empty($errors)) {
                                            Notification::make()
                                                ->title('Missing Gateway Information')
                                                ->body('Please configure gateway connection details first: ' . implode(', ', $errors))
                                                ->warning()
                                                ->send();
                                            return;
                                        }
                                        
                                        // Validate data point register address
                                        if (empty($itemData['register_address']) || !is_numeric($itemData['register_address'])) {
                                            Notification::make()
                                                ->title('Missing Register Address')
                                                ->body('Please set a valid register address for this data point.')
                                                ->warning()
                                                ->send();
                                            return;
                                        }
                                        
                                        // Test the data point
                                        $pollService = app(ModbusPollService::class);
                                        
                                        try {
                                            $result = $pollService->previewDataPoint([
                                                'ip_address' => $ipAddress,
                                                'port' => (int) $port,
                                                'unit_id' => (int) $unitId,
                                            ], $itemData);
                                            
                                            if ($result->success) {
                                                Notification::make()
                                                    ->title('Data Point Preview')
                                                    ->body("Raw value: {$result->rawValue}, Processed value: {$result->processedValue}")
                                                    ->success()
                                                    ->send();
                                            } else {
                                                Notification::make()
                                                    ->title('Preview Failed')
                                                    ->body($result->error)
                                                    ->danger()
                                                    ->send();
                                            }
                                        } catch (\Exception $e) {
                                            Notification::make()
                                                ->title('Preview Error')
                                                ->body('Failed to preview data point: ' . $e->getMessage())
                                                ->danger()
                                                ->send();
                                        }
                                    }),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }

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