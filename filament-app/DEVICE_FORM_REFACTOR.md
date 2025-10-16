# Device Form Refactor

## Overview
Refactored the Gateway Device form into two distinct parts as requested: Common Device fields and Per Register repeater with add/remove functionality.

## Changes Made

### Form Structure
The form is now split into two main sections:

#### 1. Device Configuration Section
**Common fields that apply to all registers:**
- **Device Name** - User-friendly name (e.g., "Living Room Heater")
- **Device Type** - Energy Meter, Water Meter, or Control Device
- **Load Category** - Main Supply, AC, Sockets, Heater, Lighting, Water, Solar, Generator, Other

#### 2. Registers Section
**Per Register repeater with add/remove functionality:**
- **Register Name (Technical Label)** - Required technical identifier
- **Function** - Modbus function (3 or 4) - Required
- **Register** - Register address (1-65535) - Required
- **Data Type** - Int16/UInt16/Int32/UInt32/Float32/Float64 - Required
- **Byte Order** - Big Endian/Little Endian/Word Swapped - Required
- **Scale** - Scale factor (default 1.0)
- **Count** - Auto-filled from Data Type but manually overridable
- **Enabled** - Toggle for enabling/disabling the register

## Key Features

### Auto-fill Count Logic
The Count field is automatically populated based on the selected Data Type:
- **Int16/UInt16** → 1 register
- **Int32/UInt32/Float32** → 2 registers
- **Float64** → 4 registers
- Manual override is still possible

### Compact Grid Layout
- Device Configuration: 3-column grid for efficient space usage
- Registers: 8-column grid with optimal field distribution
- Maintains Filament v3 component styling

### Data Handling
- Device-level fields are automatically copied to each register
- Form lifecycle methods handle loading existing device data
- Proper validation for all required fields

### Bulk Actions
- **Enable All** - Enables all registers at once
- **Disable All** - Disables all registers at once
- Actions only visible when registers exist

## Technical Implementation

### Model Updates
- Added virtual fields to Gateway model for device-level data
- Maintains hasMany relationship with DataPoint model

### Form Lifecycle
- `mutateFormDataBeforeFill` - Loads device data from first data point
- `mutateRelationshipDataBeforeCreateUsing` - Copies device fields to new registers
- `mutateRelationshipDataBeforeSaveUsing` - Copies device fields to existing registers

### Validation
- All required fields are properly validated
- Numeric constraints on register addresses and counts
- Maintains existing visual style and accessibility

## Benefits
1. **Cleaner UX** - Separates device-level from register-level configuration
2. **Reduced Redundancy** - Device fields entered once, applied to all registers
3. **Auto-completion** - Smart defaults based on data type selection
4. **Bulk Operations** - Easy enable/disable of multiple registers
5. **Maintainable** - Clear separation of concerns in the form structure

The refactored form provides a much more intuitive experience for configuring devices with multiple registers while maintaining all existing functionality.