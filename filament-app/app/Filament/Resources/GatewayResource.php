<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GatewayResource\Pages;
use App\Models\Gateway;
use App\Services\GatewayManagementService;
use App\Services\ModbusPollService;
use App\Services\NotificationService;
use App\Services\GatewayStatusService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class GatewayResource extends Resource
{
    protected static ?string $model = Gateway::class;

    protected static ?string $navigationIcon = 'heroicon-o-server';

    protected static ?string $navigationLabel = 'Gateways';

    protected static ?string $modelLabel = 'Gateway';

    protected static ?string $pluralModelLabel = 'Gateways';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Gateway Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter gateway name'),
                        
                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->required()
                            ->ip()
                            ->placeholder('192.168.1.100'),
                        
                        Forms\Components\TextInput::make('port')
                            ->required()
                            ->numeric()
                            ->default(502)
                            ->minValue(1)
                            ->maxValue(65535)
                            ->placeholder('502'),
                        
                        Forms\Components\TextInput::make('unit_id')
                            ->label('Unit ID')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(255)
                            ->placeholder('1'),
                        
                        Forms\Components\TextInput::make('poll_interval')
                            ->label('Poll Interval (seconds)')
                            ->required()
                            ->numeric()
                            ->default(10)
                            ->minValue(1)
                            ->maxValue(3600)
                            ->placeholder('10'),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Enable polling for this gateway'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Data Points')
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('connection_info')
                    ->label('IP:Port')
                    ->getStateUsing(fn (Gateway $record): string => "{$record->ip_address}:{$record->port}")
                    ->searchable(['ip_address'])
                    ->sortable(['ip_address', 'port']),
                
                TextColumn::make('unit_id')
                    ->label('Unit ID')
                    ->sortable(),
                
                TextColumn::make('poll_interval')
                    ->label('Poll Interval')
                    ->formatStateUsing(fn (string $state): string => "{$state}s")
                    ->sortable(),
                
                TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->placeholder('Never'),
                
                TextColumn::make('counters')
                    ->label('Success/Fail')
                    ->getStateUsing(fn (Gateway $record): string => "{$record->success_count}/{$record->failure_count}")
                    ->alignCenter(),
                
                TextColumn::make('status')
                    ->getStateUsing(function (Gateway $record): string {
                        return app(GatewayStatusService::class)->computeStatus($record);
                    })
                    ->formatStateUsing(function (string $state): string {
                        return app(GatewayStatusService::class)->getStatusLabel($state);
                    })
                    ->badge()
                    ->color(function (string $state): string {
                        return app(GatewayStatusService::class)->getStatusBadgeColor($state);
                    })
                    ->extraAttributes(function (Gateway $record): array {
                        $status = app(GatewayStatusService::class)->computeStatus($record);
                        $label = app(GatewayStatusService::class)->getStatusLabel($status);
                        return [
                            'aria-label' => "Gateway status: {$label}",
                            'role' => 'status',
                        ];
                    }),
                
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),
            ])
            ->actions([
                Action::make('test_connection')
                    ->icon('heroicon-o-radio')
                    ->tooltip('Test connection')
                    ->label(null)
                    ->button()
                    ->color('warning')
                    ->extraAttributes([
                        'aria-label' => 'Test gateway connection',
                        'role' => 'button',
                        'tabindex' => '0',
                    ])
                    ->action(function (Gateway $record) {
                        $pollService = app(ModbusPollService::class);
                        $notificationService = app(NotificationService::class);
                        
                        $result = $pollService->testConnection(
                            $record->ip_address,
                            $record->port,
                            $record->unit_id
                        );
                        
                        if ($result->success) {
                            $notificationService->connectionTest(
                                true,
                                $result->latency,
                                $result->testValue
                            );
                        } else {
                            $notificationService->error(
                                $result->error,
                                $result->diagnosticInfo,
                                false
                            );
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Test Gateway Connection')
                    ->modalDescription('This will attempt to connect to the gateway and read a test register.')
                    ->modalSubmitActionLabel('Test Connection'),
                
                Action::make('pause')
                    ->icon('heroicon-o-pause')
                    ->tooltip('Pause polling')
                    ->label(null)
                    ->button()
                    ->color('warning')
                    ->extraAttributes([
                        'aria-label' => 'Pause gateway polling',
                        'role' => 'button',
                        'tabindex' => '0',
                    ])
                    ->action(function (Gateway $record) {
                        $service = app(GatewayManagementService::class);
                        $notificationService = app(NotificationService::class);
                        
                        $originalState = $record->is_active;
                        $service->pausePolling($record);
                        
                        $notificationService->gatewayOperation(
                            'pause',
                            $record->name,
                            true,
                            function () use ($service, $record, $originalState) {
                                if ($originalState) {
                                    $service->resumePolling($record);
                                }
                            }
                        );
                    })
                    ->visible(fn (Gateway $record): bool => $record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Pause Gateway Polling')
                    ->modalDescription('This will stop polling this gateway until you restart it.')
                    ->modalSubmitActionLabel('Pause Polling'),
                
                Action::make('resume')
                    ->icon('heroicon-o-play')
                    ->tooltip('Resume polling')
                    ->label(null)
                    ->button()
                    ->color('success')
                    ->extraAttributes([
                        'aria-label' => 'Resume gateway polling',
                        'role' => 'button',
                        'tabindex' => '0',
                    ])
                    ->action(function (Gateway $record) {
                        $service = app(GatewayManagementService::class);
                        $notificationService = app(NotificationService::class);
                        
                        $originalState = $record->is_active;
                        $service->resumePolling($record);
                        
                        $notificationService->gatewayOperation(
                            'resume',
                            $record->name,
                            true,
                            function () use ($service, $record, $originalState) {
                                if (!$originalState) {
                                    $service->pausePolling($record);
                                }
                            }
                        );
                    })
                    ->visible(fn (Gateway $record): bool => !$record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Resume Gateway Polling')
                    ->modalDescription('This will resume polling for this gateway.')
                    ->modalSubmitActionLabel('Resume Polling'),
                
                Tables\Actions\EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->tooltip('Edit gateway')
                    ->label(null)
                    ->button()
                    ->extraAttributes([
                        'aria-label' => 'Edit gateway',
                        'role' => 'button',
                        'tabindex' => '0',
                    ])
                    ->slideOver(),
                
                Tables\Actions\DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->tooltip('Delete gateway')
                    ->label(null)
                    ->button()
                    ->extraAttributes([
                        'aria-label' => 'Delete gateway',
                        'role' => 'button',
                        'tabindex' => '0',
                    ])
                    ->action(function (Gateway $record) {
                        $service = app(GatewayManagementService::class);
                        $notificationService = app(NotificationService::class);
                        
                        $gatewayData = $record->toArray();
                        $success = $service->deleteGateway($record);
                        
                        $notificationService->gatewayOperation(
                            'delete',
                            $gatewayData['name'],
                            $success
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('pause_selected')
                        ->label('Pause Selected')
                        ->icon('heroicon-o-pause')
                        ->color('warning')
                        ->action(function ($records) {
                            $service = app(GatewayManagementService::class);
                            $notificationService = app(NotificationService::class);
                            $count = 0;
                            $total = count($records);
                            
                            foreach ($records as $record) {
                                if ($record->is_active) {
                                    $service->pausePolling($record);
                                    $count++;
                                }
                            }
                            
                            $notificationService->bulkOperation('pause', $count, $total);
                        })
                        ->requiresConfirmation(),
                    
                    Tables\Actions\BulkAction::make('resume_selected')
                        ->label('Resume Selected')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->action(function ($records) {
                            $service = app(GatewayManagementService::class);
                            $notificationService = app(NotificationService::class);
                            $count = 0;
                            $total = count($records);
                            
                            foreach ($records as $record) {
                                if (!$record->is_active) {
                                    $service->resumePolling($record);
                                    $count++;
                                }
                            }
                            
                            $notificationService->bulkOperation('resume', $count, $total);
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGateways::route('/'),
            'create' => Pages\CreateGateway::route('/create'),
            'view' => Pages\ViewGateway::route('/{record}'),
            'edit' => Pages\EditGateway::route('/{record}/edit'),
        ];
    }
}