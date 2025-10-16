<?php

namespace Database\Seeders;

use App\Models\DataPoint;
use App\Models\Reading;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WeeklyReadingsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Generating 7 days of realistic meter readings...');
        
        // Get all monitoring datapoints
        $dataPoints = DataPoint::where('application', 'monitoring')->get();
        
        if ($dataPoints->isEmpty()) {
            $this->command->error('No monitoring datapoints found!');
            return;
        }
        
        // Clear existing readings for clean data
        $this->command->info('Clearing existing readings...');
        Reading::whereIn('data_point_id', $dataPoints->pluck('id'))->delete();
        
        foreach ($dataPoints as $dataPoint) {
            $this->generateReadingsForDataPoint($dataPoint);
        }
        
        $this->command->info('✅ Generated realistic 7-day readings for all monitoring devices!');
    }
    
    private function generateReadingsForDataPoint(DataPoint $dataPoint): void
    {
        $this->command->info("Generating readings for: {$dataPoint->label}");
        
        // Define realistic consumption patterns based on device type
        $patterns = $this->getConsumptionPatterns();
        $pattern = $patterns[$dataPoint->load_type] ?? $patterns['other'];
        
        // Starting cumulative value (simulate existing meter reading)
        $baseValue = $pattern['base_value'];
        $currentValue = $baseValue;
        
        // Generate readings for last 7 days, every 30 minutes
        $startTime = now()->subDays(7)->startOfDay();
        $endTime = now();
        
        $readings = [];
        $currentTime = $startTime->copy();
        
        while ($currentTime <= $endTime) {
            // Calculate realistic consumption for this 30-minute interval
            $consumption = $this->calculateConsumption($currentTime, $pattern);
            $currentValue += $consumption;
            
            $readings[] = [
                'data_point_id' => $dataPoint->id,
                'raw_value' => (int)$currentValue,
                'scaled_value' => round($currentValue, 2),
                'quality' => 'good',
                'read_at' => $currentTime->toDateTimeString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            $currentTime->addMinutes(30);
        }
        
        // Insert in batches for performance
        $chunks = array_chunk($readings, 100);
        foreach ($chunks as $chunk) {
            Reading::insert($chunk);
        }
        
        $this->command->info("  ✓ Generated " . count($readings) . " readings");
    }
    
    private function getConsumptionPatterns(): array
    {
        return [
            'power' => [
                'base_value' => 4500.0, // Starting kWh
                'hourly_base' => 2.5,   // Base kWh per hour
                'peak_multiplier' => 2.0, // Peak usage multiplier
                'night_multiplier' => 0.3, // Night usage multiplier
                'weekend_multiplier' => 0.7, // Weekend multiplier
                'peak_hours' => [8, 9, 10, 18, 19, 20], // Peak consumption hours
            ],
            'ac' => [
                'base_value' => 3200.0,
                'hourly_base' => 3.5,
                'peak_multiplier' => 2.5,
                'night_multiplier' => 0.1,
                'weekend_multiplier' => 0.8,
                'peak_hours' => [11, 12, 13, 14, 15, 16, 17],
            ],
            'socket' => [
                'base_value' => 1800.0,
                'hourly_base' => 1.2,
                'peak_multiplier' => 1.8,
                'night_multiplier' => 0.2,
                'weekend_multiplier' => 0.6,
                'peak_hours' => [9, 10, 11, 14, 15, 16],
            ],
            'water' => [
                'base_value' => 850.0,
                'hourly_base' => 0.8,
                'peak_multiplier' => 3.0,
                'night_multiplier' => 0.1,
                'weekend_multiplier' => 1.2,
                'peak_hours' => [7, 8, 12, 18, 19],
            ],
            'other' => [
                'base_value' => 1000.0,
                'hourly_base' => 1.0,
                'peak_multiplier' => 1.5,
                'night_multiplier' => 0.4,
                'weekend_multiplier' => 0.8,
                'peak_hours' => [9, 10, 11, 14, 15],
            ],
        ];
    }
    
    private function calculateConsumption(Carbon $time, array $pattern): float
    {
        $hour = $time->hour;
        $isWeekend = $time->isWeekend();
        $isPeakHour = in_array($hour, $pattern['peak_hours']);
        $isNightTime = $hour >= 22 || $hour <= 6;
        
        // Base consumption for 30 minutes (half hour)
        $baseConsumption = $pattern['hourly_base'] / 2;
        
        // Apply time-based multipliers
        if ($isNightTime) {
            $baseConsumption *= $pattern['night_multiplier'];
        } elseif ($isPeakHour) {
            $baseConsumption *= $pattern['peak_multiplier'];
        }
        
        // Apply weekend multiplier
        if ($isWeekend) {
            $baseConsumption *= $pattern['weekend_multiplier'];
        }
        
        // Add some random variation (±20%)
        $variation = 1 + (mt_rand(-20, 20) / 100);
        $baseConsumption *= $variation;
        
        // Ensure minimum consumption
        return max(0.01, $baseConsumption);
    }
}