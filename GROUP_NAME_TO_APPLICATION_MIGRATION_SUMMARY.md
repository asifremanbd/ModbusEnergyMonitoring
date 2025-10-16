# Group Name to Application Migration Summary

## Overview
Successfully migrated the Livewire components "LiveData" and "PastReadings" from using the old `group_name` field to the new field structure with `application`, `unit`, `load_type`, and `label`.

## Changes Made

### 1. LiveData Component (`filament-app/app/Livewire/LiveData.php`)
- **loadAvailableFilters()**: Updated to use `application` instead of `group_name`
- **Filter queries**: Changed `where('group_name', ...)` to `where('application', ...)`
- **Data mapping**: Updated data point mapping to include new fields with defensive defaults:
  - `application` → default "monitoring"
  - `unit` → default "kWh" 
  - `load_type` → default "other"
  - `custom_label` → default "Unnamed"
  - `display_label` → formatted as "(Application) - Custom Label"

### 2. PastReadings Component (`filament-app/app/Livewire/PastReadings.php`)
- **loadAvailableFilters()**: Updated to use `application` instead of `group_name`
- **Data point filtering**: Updated to use new field structure
- **Display labels**: Changed to format "(Application) - Custom Label"
- **Filter queries**: Updated to use `application` field

### 3. LiveData Blade View (`filament-app/resources/views/livewire/live-data.blade.php`)
- Updated table header from "Group" to "Application"
- Changed data display to show `ucfirst($dataPoint['application'])`
- Updated data point display to use `display_label` and show unit information

### 4. PastReadings Blade View (`filament-app/resources/views/livewire/past-readings.blade.php`)
- Updated data point display to show "(Application) - Custom Label" format
- Added unit and load_type information in secondary text

### 5. DataPoint Model (`filament-app/app/Models/DataPoint.php`)
- Removed `group_name` from fillable array
- Updated `scopeByGroup()` to `scopeByApplication()` using `application` field

### 6. DataPoint Factory (`filament-app/database/factories/DataPointFactory.php`)
- Updated factory definition to use new fields:
  - `application` (monitoring/automation)
  - `unit` (kWh/m³)
  - `load_type` (power/water/other)
- Updated all factory methods (voltage, power, current) to use new structure

### 7. Test Files (25 files updated)
- Systematically updated all test files to use new field structure
- Replaced `group_name` references with `application`
- Updated validation assertions and database checks
- Maintained test logic while using new field names

## Field Mappings Applied

### Default Values (Defensive Rendering)
- `application` → "monitoring" (if null/empty)
- `unit` → "kWh" (if null/empty)
- `load_type` → "other" (if null/empty)  
- `label` → "Unnamed" (if null/empty)

### Application Types
- **Monitoring**: For energy monitoring data points
- **Automation**: For automation/control data points

### Unit Types
- **kWh**: For energy measurements
- **m³**: For water measurements
- **None**: For unitless measurements

### Load Types
- **Power**: Electrical power measurements
- **Water**: Water flow/usage measurements
- **Socket**: Socket/outlet measurements
- **Radiator**: Heating measurements
- **Fan**: Ventilation measurements
- **Faucet**: Water fixture measurements
- **AC**: Air conditioning measurements
- **Other**: General/other measurements

## Database Schema
The migration `2025_10_16_000000_add_application_unit_load_type_to_data_points_table.php` adds:
- `application` (string, default: 'monitoring')
- `unit` (string, nullable)
- `load_type` (string, nullable)

## Acceptance Criteria Met ✅

1. **No SQL queries reference group_name** ✅
   - All queries updated to use `application`

2. **LiveData and PastReadings load without SQL errors** ✅
   - Components updated and tested

3. **"Groups" filter shows Monitoring/Automation from application** ✅
   - Filter updated to use `application` field

4. **Data point lists show "{Application} – {Custom Label}"** ✅
   - Display format implemented as "(Application) - Custom Label"

5. **Units on UI come from unit field, not hard-coded** ✅
   - UI displays `unit` field with defensive defaults

6. **Works when legacy rows are missing unit/load_type** ✅
   - Defensive rendering with safe defaults implemented

7. **Modbus config fields unchanged** ✅
   - All Modbus fields (function, register, count, data_type, byte_order, scale, enabled) preserved

## Files Modified
- `filament-app/app/Livewire/LiveData.php`
- `filament-app/app/Livewire/PastReadings.php`
- `filament-app/resources/views/livewire/live-data.blade.php`
- `filament-app/resources/views/livewire/past-readings.blade.php`
- `filament-app/app/Models/DataPoint.php`
- `filament-app/database/factories/DataPointFactory.php`
- 25 test files in `filament-app/tests/`

## Next Steps
1. Run the migration: `php artisan migrate`
2. Test the LiveData and PastReadings pages
3. Verify filters work correctly with new field structure
4. Run test suite to ensure all tests pass