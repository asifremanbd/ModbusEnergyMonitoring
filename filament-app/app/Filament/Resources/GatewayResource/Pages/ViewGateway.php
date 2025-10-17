<?php

namespace App\Filament\Resources\GatewayResource\Pages;

use App\Filament\Resources\GatewayResource;
use App\Models\Gateway;
use App\Services\GatewayManagementService;
use App\Services\ModbusPollService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;

class ViewGateway extends ViewRecord
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
            
            Actions\Action::make('pause_resume')
                ->label(fn (): string => $this->record->is_active ? 'Pause Polling' : 'Resume Polling')
                ->icon(fn (): string => $this->record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                ->color(fn (): string => $this->record->is_active ? 'warning' : 'success')
                ->action(function () {
                    $service = app(GatewayManagementService::class);
                    
                    if ($this->record->is_active) {
                        $service->pausePolling($this->record);
                        $message = 'Registration polling paused';
                    } else {
                        $service->resumePolling($this->record);
                        $message = 'Registration polling resumed';
                    }
                    
                    $this->record->refresh();
                    
                    Notification::make()
                        ->title($message)
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),
            
            Actions\Action::make('reset_counters')
                ->label('Reset Counters')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    $service = app(GatewayManagementService::class);
                    $service->resetCounters($this->record);
                    $this->record->refresh();
                    
                    Notification::make()
                        ->title('Counters Reset')
                        ->body('Success and failure counters have been reset to zero.')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),
            
            Actions\EditAction::make(),
            
            Actions\DeleteAction::make()
                ->action(function () {
                    $service = app(GatewayManagementService::class);
                    $service->deleteGateway($this->record);
                    
                    return redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Modbus Registration Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Registration Name'),
                        
                        Infolists\Components\TextEntry::make('ip_address')
                            ->label('IP Address'),
                        
                        Infolists\Components\TextEntry::make('port')
                            ->label('Port'),
                        
                        Infolists\Components\TextEntry::make('unit_id')
                            ->label('Unit ID'),
                        
                        Infolists\Components\TextEntry::make('poll_interval')
                            ->label('Poll Interval')
                            ->formatStateUsing(fn (string $state): string => "{$state} seconds"),
                        
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                    ])
                    ->columns(2),
                
                Infolists\Components\Section::make('Status & Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->label('Current Status')
                            ->getStateUsing(function (Gateway $record): string {
                                if (!$record->is_active) {
                                    return 'Disabled';
                                }
                                return $record->is_online ? 'Online' : 'Offline';
                            })
                            ->badge()
                            ->color(function (Gateway $record): string {
                                if (!$record->is_active) {
                                    return 'gray';
                                }
                                return $record->is_online ? 'success' : 'danger';
                            }),
                        
                        Infolists\Components\TextEntry::make('last_seen_at')
                            ->label('Last Seen')
                            ->dateTime('M j, Y H:i:s')
                            ->placeholder('Never'),
                        
                        Infolists\Components\TextEntry::make('success_count')
                            ->label('Successful Polls'),
                        
                        Infolists\Components\TextEntry::make('failure_count')
                            ->label('Failed Polls'),
                        
                        Infolists\Components\TextEntry::make('success_rate')
                            ->label('Success Rate')
                            ->getStateUsing(fn (Gateway $record): string => number_format($record->success_rate, 1) . '%'),
                        
                        Infolists\Components\TextEntry::make('total_polls')
                            ->label('Total Polls')
                            ->getStateUsing(fn (Gateway $record): string => (string) ($record->success_count + $record->failure_count)),
                    ])
                    ->columns(3),
                
                Infolists\Components\Section::make('Data Points')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('dataPoints')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('group_name')
                                    ->label('Group'),
                                
                                Infolists\Components\TextEntry::make('label')
                                    ->label('Label'),
                                
                                Infolists\Components\TextEntry::make('modbus_function')
                                    ->label('Function')
                                    ->formatStateUsing(fn (string $state): string => match($state) {
                                        '3' => '3 - Holding Registers',
                                        '4' => '4 - Input Registers',
                                        default => $state
                                    }),
                                
                                Infolists\Components\TextEntry::make('register_address')
                                    ->label('Register'),
                                
                                Infolists\Components\TextEntry::make('data_type')
                                    ->label('Data Type')
                                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                                
                                Infolists\Components\IconEntry::make('is_enabled')
                                    ->label('Enabled')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check')
                                    ->falseIcon('heroicon-o-x-mark')
                                    ->trueColor('success')
                                    ->falseColor('danger'),
                            ])
                            ->columns(6)
                            ->contained(false),
                    ])
                    ->collapsible()
                    ->collapsed(fn (Gateway $record): bool => $record->dataPoints->count() > 5),
                
                Infolists\Components\Section::make('Timestamps')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('M j, Y H:i:s'),
                        
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('M j, Y H:i:s'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}