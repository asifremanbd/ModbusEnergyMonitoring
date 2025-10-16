# Historical Data Display Implementation

## Problem
When gateways go offline or polling is disabled, the dashboard was showing "No Recent Data" message instead of displaying the last available data from the database.

## Solution
Modified the dashboard logic to:

1. **Show Last Available Data**: Instead of requiring recent readings (within 1 hour), the dashboard now shows historical data when no recent data is available.

2. **Visual Indicators**: Added a warning banner at the top of the dashboard when displaying historical data to inform users that the data is not live.

3. **Fallback Data Loading**: 
   - KPIs now use last 100 readings if no recent data exists
   - Gateway status shows last available readings for sparkline charts
   - Weekly meter cards calculate usage from any available historical readings

## Changes Made

### Dashboard.php
- Modified `checkEmptyState()` to only show empty state when NO data exists (not just no recent data)
- Updated `loadKpis()` to fallback to historical readings for success rate calculation
- Enhanced `loadGateways()` to show last available readings when no recent data exists
- Modified `loadWeeklyMeterCards()` to use historical data when recent data is unavailable
- Added `calculateUsageFromReadings()` helper method for historical usage calculation

### WeeklyMeterCards.php
- Updated `getStats()` to fallback to historical readings when no recent weekly data exists
- Added `calculateUsageFromReadings()` helper method

### dashboard.blade.php
- Added warning banner to indicate when historical data is being displayed

## User Experience
- Users now see their last available data instead of empty screens
- Clear visual indication when data is historical vs live
- All charts and metrics continue to function with historical data
- Dashboard remains useful even when gateways are offline

## Benefits
- Better user experience during gateway outages
- Maintains dashboard functionality with historical context
- Clear distinction between live and historical data
- No data loss visibility during maintenance or connectivity issues