# WeeklyMeterCards Widget

## Overview
The `WeeklyMeterCards` widget displays weekly usage summaries for each meter (DataPoint) in the system. It shows consumption data for the last 7 days with sparkline charts and color-coded usage levels.

## Features
- **Per-meter cards**: One card for each enabled DataPoint
- **Weekly consumption**: Calculates total usage over the last 7 days
- **Daily sparklines**: Shows daily usage trends as small charts
- **Smart unit detection**: Automatically detects kWh, m³, or generic units
- **Color coding**: Visual indicators based on usage levels
- **Performance optimized**: Uses caching and efficient queries
- **Responsive design**: Adapts to different screen sizes

## Usage Calculation
The widget calculates consumption by:
1. Fetching readings from the last 8 days (to ensure complete 7-day coverage)
2. Grouping readings by day
3. For each day, subtracting the first reading from the last reading
4. Summing daily consumption to get weekly totals

## Unit Detection
Units are automatically detected based on DataPoint labels and group names:
- **Energy meters**: kWh (keywords: energy, kwh, kilowatt, power, electricity)
- **Water meters**: m³ (keywords: water, m³, cubic, liter, flow)
- **Gas meters**: m³ (keywords: gas, natural gas, propane)
- **Default**: units

## Color Coding
- **Green (success)**: Low usage
- **Yellow (warning)**: Medium usage  
- **Red (danger)**: High usage
- **Blue (primary)**: Unknown meter type

Thresholds:
- Energy: >100 kWh (red), >50 kWh (yellow)
- Water/Gas: >50 m³ (red), >25 m³ (yellow)

## Performance
- **Caching**: Results cached for 5 minutes
- **Polling**: Updates every 30 seconds
- **Efficient queries**: Uses eager loading and filtered queries
- **Lazy loading**: Compatible with Filament v3 lazy loading

## Requirements
- DataPoint model with enabled scope
- Reading model with quality filtering
- Gateway relationship for display names