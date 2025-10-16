# Weekly Monitoring Widget Implementation

## Overview
Created a Filament dashboard widget that displays weekly usage cards specifically for Weekly Monitoring datapoints.

## Features

### Data Filtering
- Only shows datapoints where `application = 'monitoring'`
- Excludes Automation items (separate widget planned)
- Uses enabled datapoints only

### Card Layout
Each card displays:
- **Left side**: 
  - Custom label from `label` field
  - Big number: `total_usage` with `unit` (kWh, m³, None)
  - Subtext: "Daily average: X {unit}/day"
  - Simple sparkline placeholder (visual progress bar)

- **Right side**: 
  - Icon from `public/images/icons/` based on `load_type`

### Icon Mapping
Maps `load_type` values to local icon files:
- `Power` → `power-meter.png`
- `Water` → `water-meter.png`
- `Socket` → `supply.png`
- `Radiator` → `radiator.png`
- `Fan` → `fan.png`
- `Faucet` → `faucet.png`
- `AC` → `electric-meter.png`
- `Other` → `statistics.png`

### Technical Details
- **Widget Class**: `App\Filament\Widgets\WeeklyMeterCards`
- **View**: `filament.widgets.weekly-meter-cards`
- **Caching**: 5-minute cache for performance
- **Polling**: 30-second refresh interval
- **Responsive**: Grid layout (1 col mobile, 2 col tablet, 3 col desktop)

### Data Calculation
- Calculates weekly usage from cumulative meter readings
- Handles missing data gracefully
- Shows daily averages based on actual time periods
- Fallback to available data if less than 7 days

## Files Modified
1. `app/Filament/Widgets/WeeklyMeterCards.php` - Widget logic
2. `resources/views/filament/widgets/weekly-meter-cards.blade.php` - Card layout

## Usage
The widget automatically appears on the Filament dashboard and shows all monitoring datapoints with their current usage statistics.