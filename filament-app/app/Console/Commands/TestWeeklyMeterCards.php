<?php

namespace App\Console\Commands;

use App\Filament\Widgets\WeeklyMeterCards;
use App\Models\DataPoint;
use Illuminate\Console\Command;

class TestWeeklyMeterCards extends Command
{
    protected $signature = 'test:weekly-meter-cards';
    protected $description = 'Test the WeeklyMeterCards widget functionality';

    public function handle()
    {
        $this->info('Testing WeeklyMeterCards widget...');
        
        // Check if we have data points
        $dataPointsCount = DataPoint::enabled()->count();
        $this->info("Found {$dataPointsCount} enabled data points");
        
        if ($dataPointsCount === 0) {
            $this->warn('No enabled data points found. The widget will show "No Data Available".');
            return;
        }
        
        // Test widget instantiation
        try {
            $widget = new WeeklyMeterCards();
            $this->info('Widget instantiated successfully');
            
            // Test stats generation (using reflection to access protected method)
            $reflection = new \ReflectionClass($widget);
            $getStatsMethod = $reflection->getMethod('getStats');
            $getStatsMethod->setAccessible(true);
            
            $stats = $getStatsMethod->invoke($widget);
            $this->info('Generated ' . count($stats) . ' stat cards');
            
            foreach ($stats as $index => $stat) {
                $this->line("Card " . ($index + 1) . ": " . $stat->getLabel() . " - " . $stat->getValue());
            }
            
            $this->info('✅ WeeklyMeterCards widget test completed successfully!');
            
        } catch (\Exception $e) {
            $this->error('❌ Widget test failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
}