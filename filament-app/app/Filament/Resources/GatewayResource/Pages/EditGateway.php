<?php

namespace App\Filament\Resources\GatewayResource\Pages;

use App\Filament\Resources\GatewayResource;
use App\Services\GatewayManagementService;
use App\Services\ModbusPollService;
use App\Services\AutomationControlService;
use App\Models\DataPoint;
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
                Forms\Components\Section::make('Modbus Registration Configuration')
                    ->description('Essential Modbus registration settings')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter unique registration name')
                            ->helperText('Descriptive name to identify this Modbus registration'),
                        
                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->required()
                            ->ip()
                            ->placeholder('192.168.1.100')
                            ->helperText('Static or public IP of the Teltonika device'),
                        
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
                            ->helperText('Enable polling for this registration (default ON)'),
                    ])
                    ->columns(2)
                    ->compact(),
                
                Forms\Components\Section::make('Data Points')
                    ->description('Configure data points for this registration')
                    ->schema([
                        Forms\Components\Placeholder::make('bulk_actions_info')
                            ->label('Bulk Actions')
                            ->content('Use the "Enable All Points" and "Disable All Points" buttons in the page header for bulk operations.')
                            ->visible(fn (Forms\Get $get) => !empty($get('dataPoints'))),
                        
                        Forms\Components\Repeater::make('dataPoints')
                            ->relationship('dataPoints')
                            ->schema([
                                // Basic Configuration Row
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
                                                '°C' => '°C',
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
                                            ->placeholder('Warehouse Fan')
                                            ->columnSpan(2),
                                        
                                        Forms\Components\Select::make('modbus_function')
                                            ->label('Function')
                                            ->options([
                                                3 => '3 (Holding)',
                                                4 => '4 (Input)',
                                            ])
                                            ->default(4)
                                            ->required()
                                            ->visible(fn (Forms\Get $get): bool => $get('application') === 'monitoring')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\TextInput::make('register_address')
                                            ->label('Register')
                                            ->required()
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(65535)
                                            ->placeholder('1')
                                            ->visible(fn (Forms\Get $get): bool => $get('application') === 'monitoring')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\TextInput::make('register_count')
                                            ->label('Count')
                                            ->numeric()
                                            ->default(2)
                                            ->minValue(1)
                                            ->maxValue(4)
                                            ->placeholder('2')
                                            ->visible(fn (Forms\Get $get): bool => $get('application') === 'monitoring')
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
                                            ->visible(fn (Forms\Get $get): bool => $get('application') === 'monitoring')
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
                                            ->visible(fn (Forms\Get $get): bool => $get('application') === 'monitoring')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\TextInput::make('scale_factor')
                                            ->label('Scale')
                                            ->numeric()
                                            ->default(1.0)
                                            ->step(0.000001)
                                            ->placeholder('1.0')
                                            ->visible(fn (Forms\Get $get): bool => $get('application') === 'monitoring')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\Toggle::make('is_enabled')
                                            ->label('Enabled')
                                            ->default(true)
                                            ->columnSpan(1),
                                    ]),

                                // Automation Control Section
                                Forms\Components\Section::make('Automation Control')
                                    ->description('Write-capable control panel for automation points')
                                    ->visible(fn (Forms\Get $get): bool => $get('application') === 'automation')
                                    ->schema([
                                        // Status Badge
                                        Forms\Components\Placeholder::make('status_badge')
                                            ->label('Current State')
                                            ->content(fn (Forms\Get $get): string => 
                                                match($get('last_command_state')) {
                                                    'on' => '🟢 ON',
                                                    'off' => '🟡 OFF', 
                                                    'error' => '🔴 ERROR',
                                                    default => '⚪ IDLE'
                                                }
                                            ),

                                        // Write Mapping
                                        Forms\Components\Fieldset::make('Write Mapping')
                                            ->schema([
                                                Forms\Components\Select::make('function_code')
                                                    ->label('Function Code')
                                                    ->options([
                                                        5 => 'FC05 (Write Single Coil)',
                                                        6 => 'FC06 (Write Single Register)',
                                                        15 => 'FC15 (Write Multiple Coils)',
                                                        16 => 'FC16 (Write Multiple Registers)',
                                                    ])
                                                    ->default(5)
                                                    ->required()
                                                    ->reactive(),
                                                
                                                Forms\Components\TextInput::make('write_address')
                                                    ->label('Write Address')
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->maxValue(65535)
                                                    ->placeholder('2001'),
                                                
                                                Forms\Components\TextInput::make('count')
                                                    ->label('Count')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->minValue(1)
                                                    ->maxValue(4),
                                                
                                                Forms\Components\Select::make('write_data_type')
                                                    ->label('Write Data Type')
                                                    ->options([
                                                        'bool' => 'Bool',
                                                        'int16' => 'Int16',
                                                        'uint16' => 'UInt16',
                                                        'int32' => 'Int32',
                                                        'float32' => 'Float32',
                                                    ])
                                                    ->default('bool')
                                                    ->reactive(),
                                                
                                                Forms\Components\Select::make('byte_order_write')
                                                    ->label('Byte Order')
                                                    ->options([
                                                        'big_endian' => 'Big Endian',
                                                        'little_endian' => 'Little Endian',
                                                        '4321' => '4321',
                                                        '2143' => '2143',
                                                    ])
                                                    ->default('big_endian')
                                                    ->visible(fn (Forms\Get $get): bool => 
                                                        in_array($get('write_data_type'), ['int32', 'float32'])
                                                    ),
                                            ])
                                            ->columns(3),

                                        // Behavior Settings
                                        Forms\Components\Fieldset::make('Behavior Settings')
                                            ->schema([
                                                Forms\Components\TextInput::make('on_value')
                                                    ->label('ON Value')
                                                    ->default('1')
                                                    ->placeholder('1'),
                                                
                                                Forms\Components\TextInput::make('off_value')
                                                    ->label('OFF Value')
                                                    ->default('0')
                                                    ->placeholder('0'),
                                                
                                                Forms\Components\TextInput::make('debounce_ms')
                                                    ->label('Debounce (ms)')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->placeholder('0'),
                                                
                                                Forms\Components\Select::make('safe_state')
                                                    ->label('Safe State')
                                                    ->options([
                                                        'off' => 'OFF',
                                                        'on' => 'ON',
                                                    ])
                                                    ->default('off'),
                                                
                                                Forms\Components\Toggle::make('inhibit')
                                                    ->label('Inhibit')
                                                    ->helperText('Blocks manual/scheduled writes when enabled')
                                                    ->default(false),
                                            ])
                                            ->columns(3),

                                        // Manual Control
                                        Forms\Components\Fieldset::make('Manual Control')
                                            ->schema([
                                                Forms\Components\Placeholder::make('control_info')
                                                    ->label('Control Actions')
                                                    ->content('Use the action buttons in the repeater item toolbar above for manual control.')
                                                    ->helperText('ON/OFF commands will be available in the item actions menu.'),
                                            ]),

                                        // Schedule Editor
                                        Forms\Components\Fieldset::make('Schedule Editor')
                                            ->schema([
                                                Forms\Components\Toggle::make('schedule_enabled')
                                                    ->label('Enable Schedule')
                                                    ->helperText('Turn on to configure weekly on/off times for this automation point')
                                                    ->default(false)
                                                    ->reactive(),
                                                
                                                Forms\Components\Grid::make(1)
                                                    ->schema([
                                                        // Monday
                                                        Forms\Components\Grid::make(6)
                                                            ->schema([
                                                                Forms\Components\Placeholder::make('monday_label')
                                                                    ->label('')
                                                                    ->content('Monday')
                                                                    ->columnSpan(1),
                                                                Forms\Components\Toggle::make('schedule_rules.monday.active')
                                                                    ->label('Active')
                                                                    ->default(true)
                                                                    ->reactive()
                                                                    ->columnSpan(1),
                                                                Forms\Components\TimePicker::make('schedule_rules.monday.on')
                                                                    ->label('Start')
                                                                    ->default('09:00')
                                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_rules.monday.active'))
                                                                    ->columnSpan(2),
                                                                Forms\Components\TimePicker::make('schedule_rules.monday.off')
                                                                    ->label('End')
                                                                    ->default('18:00')
                                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_rules.monday.active'))
                                                                    ->columnSpan(2),
                                                            ]),
                                                        
                                                        // Tuesday
                                                        Forms\Components\Grid::make(6)
                                                            ->schema([
                                                                Forms\Components\Placeholder::make('tuesday_label')
                                                                    ->label('')
                                                                    ->content('Tuesday')
                                                                    ->columnSpan(1),
                                                                Forms\Components\Toggle::make('schedule_rules.tuesday.active')
                                                                    ->label('Active')
                                                                    ->default(true)
                                                                    ->reactive()
                                                                    ->columnSpan(1),
                                                                Forms\Components\TimePicker::make('schedule_rules.tuesday.on')
                                                                    ->label('Start')
                                                                    ->default('09:00')
                                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_rules.tuesday.active'))
                                                                    ->columnSpan(2),
                                                                Forms\Components\TimePicker::make('schedule_rules.tuesday.off')
                                                                    ->label('End')
                                                                    ->default('18:00')
                                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_rules.tuesday.active'))
                                                                    ->columnSpan(2),
                                                            ]),
                                                        
                                                        // Wednesday
                                                        Forms\Components\Grid::make(6)
                                                            ->schema([
                                                                Forms\Components\Placeholder::make('wednesday_label')
                                                                    ->label('')
                                                                    ->content('Wednesday')
                                                                    ->columnSpan(1),
                                                                Forms\Components\Toggle::make('schedule_rules.wednesday.active')
                                                                    ->label('Active')
                                                                    ->default(true)
                                                                    ->reactive()
                                                                    ->columnSpan(1),
                                                                Forms\Components\TimePicker::make('schedule_rules.wednesday.on')
                                                                    ->label('Start')
                                                                    ->default('09:00')
                                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_rules.wednesday.active'))
                                                                    ->columnSpan(2),
                                                                Forms\Components\TimePicker::make('schedule_rules.wednesday.off')
                                                                    ->label('End')
                                                                    ->default('18:00')
                                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_rules.wednesday.active'))
                                                                    ->columnSpan(2),
                                                            ]),
                                                        
                                                        // Thursday
                                                        Forms\Components\Grid::make(6)
                                                            ->schema([
                                                                Forms\Components\Placeholder::make('thursday_label')
                                                                    ->label('')
                                                                    ->content('Thursday')
                                                                    ->columnSpan(1),
                                                                Forms\Components\Toggle::make('schedule_rules.thursday.active')
                                                                    ->label('Active')
                                                                    ->default(true)
                                                                    ->reactive()
                                                                    ->columnSpan(1),
                                                                Forms\Components\TimePicker::make('schedule_rules.thursday.on')
                                                                    ->label('Start')
                                                                    ->default('09:00')
                                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_rules.thursday.active'))
                                                                    ->columnSpan(2),
                                                                Forms\Components\TimePicker::make('schedule_rules.thursday.off')
                                                                    ->label('End')
                                                                    ->default('18:00')
                                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_rules.thursday.active'))
                                                                    ->columnSpan(2),
                                                            ]),
                                                        
                                                        // Friday
                                                        Forms\Components\Grid::make(6)
                                                            ->schema([
                                                                Forms\Components\Placeholder::make('friday_label')
                                                                    ->label('')
                                                                    ->content('Friday')
                                                                    ->columnSpan(1),
                                                                Forms\Components\Toggle::make('schedule_rules.friday.active')
                                                                    ->label('Active')
                                                                    ->default(true)
                                                                    ->reactive()
                                                                    ->columnSpan(1),
                                                                Forms\Components\TimePicker::make('schedule_rules.friday.on')
                                                                    ->label('Start')
                                                                    ->default('09:00')
                                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_rules.friday.active'))
                                                                    ->columnSpan(2),
                                                                Forms\Components\TimePicker::make('schedule_rules.friday.off')
                                                                    ->label('End')
                                                                    ->default('18:00')
                                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_rules.friday.active'))
                                                                    ->columnSpan(2),
                                                            ]),
                                                        
                                                        // Saturday
                                                        Forms\Components\Grid::make(6)
                                                            ->schema([
                                                                Forms\Components\Placeholder::make('saturday_label')
                                                                    ->label('')
                                                                    ->content('Saturday')
                                                                    ->columnSpan(1),
                                                                Forms\Components\Toggle::make('schedule_rules.saturday.active')
                                                                    ->label('Active')
                                                                    ->default(false)
                                                                    ->reactive()
                                                                    ->columnSpan(1),
                                                                Forms\Components\TimePicker::make('schedule_rules.saturday.on')
                                                                    ->label('Start')
                                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_rules.saturday.active'))
                                                                    ->columnSpan(2),
                                                                Forms\Components\TimePicker::make('schedule_rules.saturday.off')
                                                                    ->label('End')
                                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_rules.saturday.active'))
                                                                    ->columnSpan(2),
                                                            ]),
                                                        
                                                        // Sunday
                                                        Forms\Components\Grid::make(6)
                                                            ->schema([
                                                                Forms\Components\Placeholder::make('sunday_label')
                                                                    ->label('')
                                                                    ->content('Sunday')
                                                                    ->columnSpan(1),
                                                                Forms\Components\Toggle::make('schedule_rules.sunday.active')
                                                                    ->label('Active')
                                                                    ->default(false)
                                                                    ->reactive()
                                                                    ->columnSpan(1),
                                                                Forms\Components\TimePicker::make('schedule_rules.sunday.on')
                                                                    ->label('Start')
                                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_rules.sunday.active'))
                                                                    ->columnSpan(2),
                                                                Forms\Components\TimePicker::make('schedule_rules.sunday.off')
                                                                    ->label('End')
                                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_rules.sunday.active'))
                                                                    ->columnSpan(2),
                                                            ]),
                                                    ])
                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_enabled')),
                                                
                                                Forms\Components\TagsInput::make('schedule_rules.exceptions')
                                                    ->label('Exception Dates')
                                                    ->placeholder('2025-12-25')
                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_enabled')),
                                                
                                                Forms\Components\Grid::make(2)
                                                    ->schema([
                                                        Forms\Components\Placeholder::make('schedule_help')
                                                            ->label('Schedule Help')
                                                            ->content('Configure weekly on/off times for automation points. Use "Reset Schedule" in the header to restore default business hours.'),
                                                        
                                                        Forms\Components\Placeholder::make('schedule_preview')
                                                            ->label('Current Schedule')
                                                            ->content(function (Forms\Get $get): string {
                                                                $activeDays = 0;
                                                                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                                                
                                                                foreach ($days as $day) {
                                                                    if ($get("schedule_rules.{$day}.active")) {
                                                                        $activeDays++;
                                                                    }
                                                                }
                                                                
                                                                return $activeDays . ' active days configured';
                                                            }),
                                                    ])
                                                    ->visible(fn (Forms\Get $get): bool => $get('schedule_enabled')),
                                            ]),
                                    ])
                                    ->collapsible()
                                    ->collapsed(false),
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
                                
                                Forms\Components\Actions\Action::make('control_on')
                                    ->label('ON')
                                    ->icon('heroicon-o-power')
                                    ->color('success')
                                    ->visible(fn (array $state): bool => ($state['application'] ?? '') === 'automation')
                                    ->action(function (array $arguments, Forms\Set $set) {
                                        $itemData = $arguments['item'];
                                        $itemKey = $arguments['itemKey'] ?? null;
                                        
                                        if (!$itemKey || !isset($itemData['id'])) {
                                            Notification::make()
                                                ->title('Error')
                                                ->body('Data point not found')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        $dataPoint = DataPoint::find($itemData['id']);
                                        if (!$dataPoint) {
                                            Notification::make()
                                                ->title('Error')
                                                ->body('Data point not found in database')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        $controlService = app(AutomationControlService::class);
                                        $success = $controlService->sendOnCommand($dataPoint);
                                        
                                        if ($success) {
                                            $set("dataPoints.{$itemKey}.last_command_state", 'on');
                                            $set("dataPoints.{$itemKey}.last_command_at", now());
                                            
                                            Notification::make()
                                                ->title('ON Command Sent')
                                                ->body('Control point turned ON')
                                                ->success()
                                                ->send();
                                        } else {
                                            Notification::make()
                                                ->title('Command Failed')
                                                ->body('Unable to send ON command')
                                                ->danger()
                                                ->send();
                                        }
                                    }),
                                
                                Forms\Components\Actions\Action::make('control_off')
                                    ->label('OFF')
                                    ->icon('heroicon-o-power')
                                    ->color('warning')
                                    ->visible(fn (array $state): bool => ($state['application'] ?? '') === 'automation')
                                    ->action(function (array $arguments, Forms\Set $set) {
                                        $itemData = $arguments['item'];
                                        $itemKey = $arguments['itemKey'] ?? null;
                                        
                                        if (!$itemKey || !isset($itemData['id'])) {
                                            Notification::make()
                                                ->title('Error')
                                                ->body('Data point not found')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        $dataPoint = DataPoint::find($itemData['id']);
                                        if (!$dataPoint) {
                                            Notification::make()
                                                ->title('Error')
                                                ->body('Data point not found in database')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        $controlService = app(AutomationControlService::class);
                                        $success = $controlService->sendOffCommand($dataPoint);
                                        
                                        if ($success) {
                                            $set("dataPoints.{$itemKey}.last_command_state", 'off');
                                            $set("dataPoints.{$itemKey}.last_command_at", now());
                                            
                                            Notification::make()
                                                ->title('OFF Command Sent')
                                                ->body('Control point turned OFF')
                                                ->warning()
                                                ->send();
                                        } else {
                                            Notification::make()
                                                ->title('Command Failed')
                                                ->body('Unable to send OFF command')
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
            Actions\Action::make('enable_all_points')
                ->label('Enable All Points')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(function () {
                    $this->record->dataPoints()->update(['is_enabled' => true]);
                    
                    Notification::make()
                        ->title('All Points Enabled')
                        ->body('All data points have been enabled.')
                        ->success()
                        ->send();
                        
                    // Refresh the form
                    $this->fillForm();
                })
                ->visible(fn () => $this->record->dataPoints()->count() > 0),
            
            Actions\Action::make('disable_all_points')
                ->label('Disable All Points')
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->action(function () {
                    $this->record->dataPoints()->update(['is_enabled' => false]);
                    
                    Notification::make()
                        ->title('All Points Disabled')
                        ->body('All data points have been disabled.')
                        ->warning()
                        ->send();
                        
                    // Refresh the form
                    $this->fillForm();
                })
                ->visible(fn () => $this->record->dataPoints()->count() > 0),

            Actions\Action::make('reset_schedule')
                ->label('Reset Schedule')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    // Reset schedule to default business hours
                    $defaultSchedule = [
                        'monday' => ['active' => true, 'on' => '09:00', 'off' => '18:00'],
                        'tuesday' => ['active' => true, 'on' => '09:00', 'off' => '18:00'],
                        'wednesday' => ['active' => true, 'on' => '09:00', 'off' => '18:00'],
                        'thursday' => ['active' => true, 'on' => '09:00', 'off' => '18:00'],
                        'friday' => ['active' => true, 'on' => '09:00', 'off' => '18:00'],
                        'saturday' => ['active' => false, 'on' => '', 'off' => ''],
                        'sunday' => ['active' => false, 'on' => '', 'off' => ''],
                        'exceptions' => []
                    ];
                    
                    // Update all automation data points with default schedule
                    $this->record->dataPoints()
                        ->where('application', 'automation')
                        ->update([
                            'schedule_rules' => json_encode($defaultSchedule)
                        ]);
                    
                    Notification::make()
                        ->title('Schedule Reset')
                        ->body('All automation points reset to default business hours (Mon-Fri 9AM-6PM)')
                        ->success()
                        ->send();
                        
                    // Refresh the form
                    $this->fillForm();
                })
                ->visible(fn () => $this->record->dataPoints()->where('application', 'automation')->count() > 0),

            Actions\Action::make('test_connection')
                ->label('Test Connection')
                ->icon('heroicon-o-signal')
                ->color('info')
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