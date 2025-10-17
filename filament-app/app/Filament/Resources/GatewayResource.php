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

    protected static ?string $navigationLabel = 'Modbus Registrations';

    protected static ?string $modelLabel = 'Modbus Registration';

    protected static ?string $pluralModelLabel = 'Modbus Registrations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Modbus Registration Configuration')
                    ->description('Essential Modbus registration settings for quick setup')
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
                        'aria-label' => 'Test registration connection',
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
                    ->modalHeading('Test Registration Connection')
                    ->modalDescription('This will attempt to connect to the device and read a test register.')
                    ->modalSubmitActionLabel('Test Connection'),
                
                Action::make('pause')
                    ->icon('heroicon-o-pause')
                    ->tooltip('Pause polling')
                    ->label(null)
                    ->button()
                    ->color('warning')
                    ->extraAttributes([
                        'aria-label' => 'Pause registration polling',
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
                    ->modalHeading('Pause Registration Polling')
                    ->modalDescription('This will stop polling this registration until you restart it.')
                    ->modalSubmitActionLabel('Pause Polling'),
                
                Action::make('resume')
                    ->icon('heroicon-o-play')
                    ->tooltip('Resume polling')
                    ->label(null)
                    ->button()
                    ->color('success')
                    ->extraAttributes([
                        'aria-label' => 'Resume registration polling',
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
                    ->modalHeading('Resume Registration Polling')
                    ->modalDescription('This will resume polling for this registration.')
                    ->modalSubmitActionLabel('Resume Polling'),
                
                Tables\Actions\EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->tooltip('Edit gateway')
                    ->label(null)
                    ->button()
                    ->extraAttributes([
                        'aria-label' => 'Edit registration',
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
                        'aria-label' => 'Delete registration',
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