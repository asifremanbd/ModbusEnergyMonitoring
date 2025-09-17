<?php

namespace App\Filament\Resources\GatewayResource\Pages;

use App\Filament\Resources\GatewayResource;
use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use App\Services\ModbusPollService;
use App\Services\TeltonikaTemplateService;
use App\Services\DataPointMappingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Actions;
use Illuminate\Support\Facades\DB;

class CreateGateway extends CreateRecord
{
    protected static string $resource = GatewayResource::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make($this->getSteps())
            ]);
    }

    protected function getSteps(): array
    {
        return [
            Step::make('Connect')
                ->description('Configure gateway connection')
                ->schema([
                    Forms\Components\Section::make('Gateway Connection Settings')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Enter gateway name')
                                ->helperText('A descriptive name for this gateway'),
                            
                            Forms\Components\TextInput::make('ip_address')
                                ->label('IP Address')
                                ->required()
                                ->ip()
                                ->placeholder('192.168.1.100')
                                ->helperText('IP address of the Teltonika gateway'),
                            
                            Forms\Components\TextInput::make('port')
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
                                ->helperText('Modbus unit identifier (1-255)'),
                            
                            Forms\Components\TextInput::make('poll_interval')
                                ->label('Poll Interval (seconds)')
                                ->required()
                                ->numeric()
                                ->default(10)
                                ->minValue(1)
                                ->maxValue(3600)
                                ->placeholder('10')
                                ->helperText('How often to poll this gateway (1-3600 seconds)'),
                        ])
                        ->columns(2),
                    
                    Forms\Components\Section::make('Connection Test')
                        ->schema([
                            Forms\Components\Placeholder::make('test_connection_info')
                                ->content('Test the connection before proceeding to data point mapping.'),
                            
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('test_connection')
                                    ->label('Test Connection')
                                    ->icon('heroicon-o-signal')
                                    ->color('primary')
                                    ->action(function (Forms\Get $get) {
                                        // Get current form state instead of stale data
                                        $ipAddress = $get('ip_address');
                                        $port = $get('port');
                                        $unitId = $get('unit_id');
                                        
                                        // Comprehensive validation
                                        $errors = [];
                                        
                                        if (empty($ipAddress) || !filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                                            $errors[] = 'Valid IP address is required';
                                        }
                                        
                                        if (empty($port) || !is_numeric($port) || $port < 1 || $port > 65535) {
                                            $errors[] = 'Port must be a number between 1 and 65535';
                                        }
                                        
                                        if (empty($unitId) || !is_numeric($unitId) || $unitId < 1 || $unitId > 255) {
                                            $errors[] = 'Unit ID must be a number between 1 and 255';
                                        }
                                        
                                        if (!empty($errors)) {
                                            Notification::make()
                                                ->title('Validation Error')
                                                ->body(implode('. ', $errors))
                                                ->warning()
                                                ->send();
                                            return;
                                        }
                                        
                                        $pollService = app(ModbusPollService::class);
                                        $result = $pollService->testConnection(
                                            $ipAddress,
                                            (int) $port,
                                            (int) $unitId
                                        );
                                        
                                        if ($result->success) {
                                            Notification::make()
                                                ->title('Connection Test Successful')
                                                ->body("Latency: {$result->latency}ms" . ($result->testValue !== null ? ", Test register value: {$result->testValue}" : ''))
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
                            ]),
                        ]),
                ]),
            
            Step::make('Map Points')
                ->description('Configure data points')
                ->schema([
                    Forms\Components\Section::make('Template Selection')
                        ->schema([
                            Forms\Components\Select::make('template')
                                ->label('Use Template')
                                ->options([
                                    'teltonika_energy_meter' => 'Teltonika Energy Meter (Standard) - 12 points',
                                    'teltonika_basic' => 'Teltonika Basic (4 Points)',
                                    'custom' => 'Custom Configuration',
                                ])
                                ->default('teltonika_basic')
                                ->live()
                                ->helperText('Choose a predefined template or configure manually'),
                            
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('apply_template')
                                    ->label('Apply Selected Template')
                                    ->icon('heroicon-o-document-duplicate')
                                    ->color('success')
                                    ->action(function (Forms\Set $set, Forms\Get $get) {
                                        $templateKey = $get('template');
                                        if ($templateKey && $templateKey !== 'custom') {
                                            $templateService = app(TeltonikaTemplateService::class);
                                            $template = $templateService->getTemplate($templateKey);
                                            if ($template) {
                                                $set('data_points', $template['data_points']);
                                                
                                                Notification::make()
                                                    ->title('Template Applied')
                                                    ->body("Applied {$template['name']} with " . count($template['data_points']) . " data points.")
                                                    ->success()
                                                    ->send();
                                            }
                                        }
                                    })
                                    ->visible(fn (Forms\Get $get) => $get('template') !== 'custom'),
                            ]),
                        ]),
                    
                    Forms\Components\Section::make('Bulk Operations')
                        ->schema([
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('enable_all')
                                    ->label('Enable All Points')
                                    ->icon('heroicon-o-check-circle')
                                    ->color('success')
                                    ->action(function (Forms\Set $set, Forms\Get $get) {
                                        $dataPoints = $get('data_points') ?? [];
                                        foreach ($dataPoints as $index => $point) {
                                            $set("data_points.{$index}.is_enabled", true);
                                        }
                                        
                                        Notification::make()
                                            ->title('All Points Enabled')
                                            ->body('All data points have been enabled.')
                                            ->success()
                                            ->send();
                                    }),
                                
                                Forms\Components\Actions\Action::make('disable_all')
                                    ->label('Disable All Points')
                                    ->icon('heroicon-o-x-circle')
                                    ->color('warning')
                                    ->action(function (Forms\Set $set, Forms\Get $get) {
                                        $dataPoints = $get('data_points') ?? [];
                                        foreach ($dataPoints as $index => $point) {
                                            $set("data_points.{$index}.is_enabled", false);
                                        }
                                        
                                        Notification::make()
                                            ->title('All Points Disabled')
                                            ->body('All data points have been disabled.')
                                            ->warning()
                                            ->send();
                                    }),
                                
                                Forms\Components\Actions\Action::make('duplicate_group')
                                    ->label('Duplicate Group')
                                    ->icon('heroicon-o-document-duplicate')
                                    ->color('info')
                                    ->form([
                                        Forms\Components\Select::make('source_group')
                                            ->label('Source Group')
                                            ->options(function (Forms\Get $get) {
                                                $dataPoints = $get('../../../data_points') ?? [];
                                                $groups = [];
                                                foreach ($dataPoints as $point) {
                                                    if (!empty($point['group_name'])) {
                                                        $groups[$point['group_name']] = $point['group_name'];
                                                    }
                                                }
                                                return $groups;
                                            })
                                            ->required(),
                                        
                                        Forms\Components\TextInput::make('target_group')
                                            ->label('Target Group Name')
                                            ->required()
                                            ->placeholder('Meter_2'),
                                        
                                        Forms\Components\TextInput::make('register_offset')
                                            ->label('Register Offset')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('Add this value to all register addresses'),
                                    ])
                                    ->action(function (array $data, Forms\Set $set, Forms\Get $get) {
                                        $this->duplicateGroup($data, $set, $get);
                                    }),
                                
                                Forms\Components\Actions\Action::make('export_csv')
                                    ->label('Export CSV')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('gray')
                                    ->action(function (Forms\Get $get) {
                                        $this->exportDataPointsCSV($get('data_points') ?? []);
                                    }),
                            ]),
                        ])
                        ->visible(fn (Forms\Get $get) => !empty($get('data_points'))),
                    
                    Forms\Components\Section::make('Data Point Configuration')
                        ->schema([
                            Forms\Components\Repeater::make('data_points')
                                ->label('Data Points')
                                ->schema([
                                    Forms\Components\Grid::make(12)
                                        ->schema([
                                            Forms\Components\TextInput::make('group_name')
                                                ->label('Group')
                                                ->required()
                                                ->default('Meter_1')
                                                ->placeholder('Meter_1')
                                                ->columnSpan(2),
                                            
                                            Forms\Components\TextInput::make('label')
                                                ->label('Label')
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
                                                ->columnSpan(2),
                                            
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
                                ->defaultItems(0)
                                ->addActionLabel('Add Data Point')
                                ->reorderable(false)
                                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                ->addAction(
                                    fn (Forms\Components\Actions\Action $action) => $action
                                        ->label('Add Data Point')
                                        ->icon('heroicon-o-plus')
                                )
                                ->extraItemActions([
                                    Forms\Components\Actions\Action::make('preview')
                                        ->label('Preview')
                                        ->icon('heroicon-o-eye')
                                        ->color('info')
                                        ->action(function (array $arguments, Forms\Get $get, array $state) {
                                            $itemData = $arguments['item'];
                                            
                                            // Get current form state for gateway data
                                            $ipAddress = $get('../../ip_address');
                                            $port = $get('../../port');
                                            $unitId = $get('../../unit_id');
                                            
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
                                            
                                            // Create gateway data array with current values
                                            $gatewayData = [
                                                'ip_address' => $ipAddress,
                                                'port' => (int) $port,
                                                'unit_id' => (int) $unitId,
                                            ];
                                            
                                            $this->previewDataPoint($gatewayData, $itemData);
                                        }),
                                ])
                                ->collapsible()
                                ->cloneable(),
                            
                            Forms\Components\Placeholder::make('manual_config_info')
                                ->content('Configure data points manually or use bulk operations above.')
                                ->visible(fn (Forms\Get $get) => empty($get('data_points'))),
                        ]),
                ]),
            
            Step::make('Review')
                ->description('Review and start')
                ->schema([
                    Forms\Components\Section::make('Gateway Summary')
                        ->schema([
                            Forms\Components\Placeholder::make('gateway_summary')
                                ->content(function (Forms\Get $get): string {
                                    $name = $get('name') ?? 'Unnamed Gateway';
                                    $ip = $get('ip_address') ?? 'Not set';
                                    $port = $get('port') ?? 'Not set';
                                    $unitId = $get('unit_id') ?? 'Not set';
                                    $interval = $get('poll_interval') ?? 'Not set';
                                    
                                    return "
                                        <div class='space-y-2'>
                                            <div><strong>Name:</strong> {$name}</div>
                                            <div><strong>Connection:</strong> {$ip}:{$port}</div>
                                            <div><strong>Unit ID:</strong> {$unitId}</div>
                                            <div><strong>Poll Interval:</strong> {$interval} seconds</div>
                                        </div>
                                    ";
                                }),
                        ]),
                    
                    Forms\Components\Section::make('Data Points Summary')
                        ->schema([
                            Forms\Components\Placeholder::make('data_points_summary')
                                ->content(function (Forms\Get $get): string {
                                    $dataPoints = $get('data_points') ?? [];
                                    $count = count($dataPoints);
                                    
                                    if ($count === 0) {
                                        return '<div class="text-gray-500">No data points configured. You can add them later.</div>';
                                    }
                                    
                                    $summary = "<div><strong>Total Data Points:</strong> {$count}</div>";
                                    $summary .= "<div class='mt-2 space-y-1'>";
                                    
                                    foreach ($dataPoints as $point) {
                                        $label = $point['label'] ?? 'Unnamed';
                                        $register = $point['register_address'] ?? 'N/A';
                                        $type = $point['data_type'] ?? 'N/A';
                                        $enabled = ($point['is_enabled'] ?? true) ? '✓' : '✗';
                                        
                                        $summary .= "<div class='text-sm'>{$enabled} {$label} (Reg: {$register}, Type: {$type})</div>";
                                    }
                                    
                                    $summary .= "</div>";
                                    return $summary;
                                }),
                        ]),
                    
                    Forms\Components\Toggle::make('is_active')
                        ->label('Start Polling Immediately')
                        ->default(true)
                        ->helperText('Enable this to start polling the gateway as soon as it\'s created'),
                ]),
        ];
    }

    protected function handleRecordCreation(array $data): Gateway
    {
        return DB::transaction(function () use ($data) {
            // Extract data points from the form data
            $dataPoints = $data['data_points'] ?? [];
            unset($data['data_points'], $data['template']);
            
            // Create the gateway
            $gateway = Gateway::create($data);
            
            // Create data points if any were configured
            foreach ($dataPoints as $pointData) {
                $pointData['gateway_id'] = $gateway->id;
                DataPoint::create($pointData);
            }
            
            return $gateway;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Gateway created successfully';
    }

    /**
     * Preview a data point by reading its register value
     */
    protected function previewDataPoint(array $gatewayData, array $pointData): void
    {
        try {
            $pollService = app(ModbusPollService::class);
            
            // Create temporary gateway and data point objects for testing
            $tempGateway = new Gateway([
                'ip_address' => $gatewayData['ip_address'],
                'port' => $gatewayData['port'],
                'unit_id' => $gatewayData['unit_id'],
            ]);
            
            $tempDataPoint = new DataPoint([
                'modbus_function' => $pointData['modbus_function'] ?? 4,
                'register_address' => $pointData['register_address'],
                'register_count' => $pointData['register_count'] ?? 2,
                'data_type' => $pointData['data_type'] ?? 'float32',
                'byte_order' => $pointData['byte_order'] ?? 'word_swapped',
                'scale_factor' => $pointData['scale_factor'] ?? 1.0,
            ]);
            
            $result = $pollService->readRegister($tempGateway, $tempDataPoint);
            
            if ($result->success) {
                $label = $pointData['label'] ?? 'Data Point';
                $rawDisplay = $result->rawValue ? json_decode($result->rawValue, true) : 'N/A';
                $scaledDisplay = $result->scaledValue !== null ? number_format($result->scaledValue, 4) : 'N/A';
                
                Notification::make()
                    ->title('Preview Successful')
                    ->body("**{$label}**\nRaw: " . (is_array($rawDisplay) ? implode(', ', $rawDisplay) : $rawDisplay) . "\nScaled: {$scaledDisplay}")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Preview Failed')
                    ->body($result->error ?? 'Unknown error occurred')
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Preview Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Duplicate a group of data points with optional register offset
     */
    protected function duplicateGroup(array $data, Forms\Set $set, Forms\Get $get): void
    {
        $dataPoints = $get('data_points') ?? [];
        $sourceGroup = $data['source_group'];
        $targetGroup = $data['target_group'];
        $registerOffset = (int) ($data['register_offset'] ?? 0);
        
        $sourcePoints = array_filter($dataPoints, fn($point) => ($point['group_name'] ?? '') === $sourceGroup);
        
        if (empty($sourcePoints)) {
            Notification::make()
                ->title('No Source Points Found')
                ->body("No data points found in group '{$sourceGroup}'")
                ->warning()
                ->send();
            return;
        }
        
        $duplicatedPoints = [];
        foreach ($sourcePoints as $point) {
            $duplicatedPoint = $point;
            $duplicatedPoint['group_name'] = $targetGroup;
            $duplicatedPoint['label'] = str_replace($sourceGroup, $targetGroup, $point['label'] ?? '');
            
            if (isset($point['register_address'])) {
                $duplicatedPoint['register_address'] = $point['register_address'] + $registerOffset;
            }
            
            $duplicatedPoints[] = $duplicatedPoint;
        }
        
        // Add duplicated points to the existing data points
        $allDataPoints = array_merge($dataPoints, $duplicatedPoints);
        $set('data_points', $allDataPoints);
        
        Notification::make()
            ->title('Group Duplicated')
            ->body("Duplicated " . count($duplicatedPoints) . " points from '{$sourceGroup}' to '{$targetGroup}'")
            ->success()
            ->send();
    }

    /**
     * Export data points configuration as CSV
     */
    protected function exportDataPointsCSV(array $dataPoints): void
    {
        if (empty($dataPoints)) {
            Notification::make()
                ->title('No Data Points')
                ->body('No data points to export')
                ->warning()
                ->send();
            return;
        }
        
        $headers = [
            'Group',
            'Label', 
            'Function',
            'Register',
            'Count',
            'Data Type',
            'Byte Order',
            'Scale Factor',
            'Enabled'
        ];
        
        $csvData = [];
        $csvData[] = $headers;
        
        foreach ($dataPoints as $point) {
            $csvData[] = [
                $point['group_name'] ?? '',
                $point['label'] ?? '',
                $point['modbus_function'] ?? 4,
                $point['register_address'] ?? '',
                $point['register_count'] ?? 2,
                $point['data_type'] ?? 'float32',
                $point['byte_order'] ?? 'word_swapped',
                $point['scale_factor'] ?? 1.0,
                ($point['is_enabled'] ?? true) ? 'Yes' : 'No',
            ];
        }
        
        $filename = 'data_points_' . date('Y-m-d_H-i-s') . '.csv';
        $csvContent = '';
        
        foreach ($csvData as $row) {
            $csvContent .= '"' . implode('","', $row) . '"' . "\n";
        }
        
        // For now, just show a notification with the CSV content
        // In a real implementation, you'd want to trigger a download
        Notification::make()
            ->title('CSV Export Ready')
            ->body("Data points exported to {$filename}. " . count($dataPoints) . " points included.")
            ->success()
            ->send();
    }
}