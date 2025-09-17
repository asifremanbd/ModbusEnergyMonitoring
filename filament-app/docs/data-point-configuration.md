# Data Point Configuration Interface

This document describes the data point configuration interface implemented in the gateway creation wizard.

## Overview

The data point configuration interface allows users to configure Modbus data points for Teltonika gateways through an intuitive wizard interface. It supports template-based configuration, manual configuration, bulk operations, and real-time preview functionality.

## Features

### 1. Template Selection and Application

**Templates Available:**
- **Teltonika Energy Meter (Standard)**: 12 data points for comprehensive energy monitoring
- **Teltonika Basic (4 Points)**: Essential measurements for basic monitoring
- **Custom Configuration**: Manual configuration for specific requirements

**Template Application:**
- Select a template from the dropdown
- Click "Apply Selected Template" to populate data points
- Templates include pre-configured register addresses, data types, and scaling factors
- Energy and power measurements include appropriate scaling (kW to W, kWh to Wh)

### 2. Manual Data Point Configuration

Each data point can be configured with:

**Basic Settings:**
- **Group Name**: Logical grouping (e.g., "Meter_1", "Meter_2")
- **Label**: Descriptive name for the measurement
- **Modbus Function**: 3 (Holding Registers) or 4 (Input Registers)
- **Register Address**: Modbus register address (1-65535)
- **Register Count**: Number of registers to read (1-4)

**Data Processing:**
- **Data Type**: int16, uint16, int32, uint32, float32, float64
- **Byte Order**: Big Endian, Little Endian, Word Swapped
- **Scale Factor**: Multiplier for raw values (non-zero)
- **Enabled**: Toggle to enable/disable the data point

### 3. Bulk Operations

**Enable/Disable All Points:**
- Quickly enable or disable all configured data points
- Useful for testing or maintenance scenarios

**Duplicate Group:**
- Clone an entire group of data points to a new group
- Specify register offset to avoid conflicts
- Automatically updates group names and labels
- Useful for multi-meter installations

**Export CSV:**
- Export all data point configurations to CSV format
- Includes all configuration parameters
- Useful for documentation and backup

### 4. Point Preview Functionality

**Real-time Testing:**
- Preview individual data points before saving
- Performs actual Modbus read operation
- Shows both raw register values and scaled results
- Validates connectivity and configuration

**Requirements for Preview:**
- Gateway connection details must be configured
- Register address must be specified
- Gateway must be accessible on the network

### 5. Validation and Error Handling

**Configuration Validation:**
- Register address range validation (1-65535)
- Required field validation (group name, label, register address)
- Data type and byte order validation
- Scale factor validation (non-zero)
- Modbus function validation (3 or 4)

**Conflict Detection:**
- Register address conflict detection
- Prevents overlapping register ranges
- Clear error messages for resolution

## Usage Examples

### Basic Template Application

1. Navigate to Gateway Creation wizard
2. Complete connection details in "Connect" step
3. In "Map Points" step, select "Teltonika Basic (4 Points)"
4. Click "Apply Selected Template"
5. Review and modify data points as needed
6. Use preview functionality to test individual points

### Custom Multi-Meter Configuration

1. Start with "Teltonika Energy Meter (Standard)" template
2. Apply template to get base configuration
3. Use "Duplicate Group" to create "Meter_2"
4. Set register offset (e.g., 100) to avoid conflicts
5. Modify labels and settings for second meter
6. Preview key data points to verify configuration

### Bulk Configuration Management

1. Configure first set of data points
2. Use "Enable All Points" to activate all measurements
3. Export CSV for documentation
4. Use "Disable All Points" for maintenance mode
5. Re-enable when ready for production

## Technical Implementation

### Services Used

- **TeltonikaTemplateService**: Manages predefined templates
- **DataPointMappingService**: Validates and processes configurations
- **ModbusPollService**: Handles preview functionality and connectivity testing

### Form Components

- **Wizard Interface**: Multi-step configuration process
- **Repeater Component**: Dynamic data point management
- **Action Buttons**: Template application and bulk operations
- **Validation Rules**: Real-time form validation

### Data Flow

1. **Template Selection**: User selects template → Service provides configuration
2. **Manual Configuration**: User inputs → Validation → Form state update
3. **Bulk Operations**: User action → Service processing → Form state update
4. **Preview**: User request → Modbus read → Result display
5. **Save**: Form submission → Database transaction → Gateway creation

## Best Practices

### Template Usage
- Start with closest matching template
- Customize as needed for specific requirements
- Use preview to validate critical measurements

### Group Organization
- Use logical group names (Meter_1, Meter_2, etc.)
- Keep related measurements in same group
- Consider register layout when grouping

### Register Planning
- Plan register addresses to avoid conflicts
- Leave gaps for future expansion
- Document register mapping for maintenance

### Testing and Validation
- Always test connection before configuring points
- Preview critical data points before saving
- Verify scaling factors match device documentation
- Test with actual hardware when possible

## Troubleshooting

### Common Issues

**Preview Fails:**
- Check gateway connectivity
- Verify register address exists on device
- Confirm Modbus function is correct
- Check unit ID matches device configuration

**Template Application Issues:**
- Clear existing data points before applying new template
- Check for register conflicts after application
- Verify template matches device capabilities

**Validation Errors:**
- Ensure all required fields are filled
- Check register address is within valid range
- Verify scale factor is non-zero
- Confirm data type matches register content

### Performance Considerations

- Limit number of data points for better polling performance
- Group related registers for efficient reading
- Consider poll interval when configuring many points
- Use appropriate data types to minimize register usage