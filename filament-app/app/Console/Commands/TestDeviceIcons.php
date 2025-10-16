<?php

namespace App\Console\Commands;

use App\Models\DataPoint;
use App\Services\DeviceIconService;
use Illuminate\Console\Command;

class TestDeviceIcons extends Command
{
    protected $signature = 'test:device-icons';
    protected $description = 'Test device icon detection for existing data points';

    public function handle()
    {
        $this->info('Testing Device Icon Detection...');
        $this->newLine();

        $dataPoints = DataPoint::all();
        
        if ($dataPoints->isEmpty()) {
            $this->warn('No data points found in the database.');
            return;
        }

        $this->table(
            ['ID', 'Label', 'Group', 'Device Type', 'Icon Path'],
            $dataPoints->map(function ($dataPoint) {
                return [
                    $dataPoint->id,
                    $dataPoint->display_label ?? 'N/A',
                    $dataPoint->group_name ?? 'N/A',
                    DeviceIconService::getDeviceType($dataPoint),
                    DeviceIconService::getDeviceIcon($dataPoint)
                ];
            })->toArray()
        );

        $this->newLine();
        $this->info('Device icon detection test completed!');
        
        // Test status colors
        $this->newLine();
        $this->info('Testing status colors:');
        $statuses = ['online', 'warning', 'offline'];
        
        foreach ($statuses as $status) {
            $this->line("Status: {$status}");
            $this->line("  Color: " . DeviceIconService::getStatusColor($status));
            $this->line("  Dot: " . DeviceIconService::getStatusDotColor($status));
        }
    }
}