# Requirements Document

## Introduction

This feature implements a comprehensive modular management system in FilamentPHP that supports three hierarchical levels: Gateways → Devices → Registers. The system enables independent management of Modbus TCP gateways, their connected devices (meters/controllers), and individual Modbus registers through intuitive Filament tables and forms. This hierarchical approach provides clear organization and efficient navigation for industrial IoT device management.

## Requirements

### Requirement 1

**User Story:** As a system administrator, I want to manage Modbus TCP gateways through a dedicated interface, so that I can configure and monitor all gateway connections from a central location.

#### Acceptance Criteria

1. WHEN accessing the gateway management interface THEN the system SHALL display a Filament table listing all gateways
2. WHEN viewing the gateway table THEN the system SHALL show name, IP address, port, active status, and last seen timestamp for each gateway
3. WHEN viewing gateway statistics THEN the system SHALL display device count, success rate, and last polling time
4. WHEN clicking "Add Gateway" THEN the system SHALL open a form with fields: name, ip_address, port (default 502), unit_id (optional), poll_interval, and active status
5. WHEN clicking "Edit Gateway" THEN the system SHALL open the gateway form pre-populated with existing data
6. WHEN clicking "Delete Gateway" THEN the system SHALL prompt for confirmation and remove the gateway if confirmed
7. WHEN saving a gateway THEN the system SHALL validate IP address format and port range (1-65535)

### Requirement 2

**User Story:** As a system administrator, I want to navigate from gateway management to device management, so that I can efficiently manage devices associated with specific gateways.

#### Acceptance Criteria

1. WHEN clicking "Edit" on a gateway row THEN the system SHALL navigate to the device list for that gateway
2. WHEN viewing the device management page THEN the system SHALL display the parent gateway name in the page header
3. WHEN on the device management page THEN the system SHALL provide a breadcrumb or back button to return to gateway list

### Requirement 3

**User Story:** As a system administrator, I want to manage devices connected to each gateway, so that I can configure and monitor individual meters and control devices.

#### Acceptance Criteria

1. WHEN accessing device management for a gateway THEN the system SHALL display a Filament table listing all devices for that gateway
2. WHEN viewing the device table THEN the system SHALL show device name, type, load category, enabled status, and register count
3. WHEN clicking "Add Device" THEN the system SHALL open a form with fields: device_name, device_type (enum), load_category (enum), and enabled status
4. WHEN selecting device_type THEN the system SHALL provide options: Energy Meter, Water Meter, Control, and Other
5. WHEN selecting load_category THEN the system SHALL provide options: HVAC, Lighting, Sockets, and Other
6. WHEN clicking "Edit Device" THEN the system SHALL open the device form pre-populated with existing data
7. WHEN clicking "Delete Device" THEN the system SHALL prompt for confirmation and remove the device if confirmed
8. WHEN saving a device THEN the system SHALL automatically associate it with the current gateway

### Requirement 4

**User Story:** As a system administrator, I want to navigate from device management to register management, so that I can configure Modbus registers for specific devices.

#### Acceptance Criteria

1. WHEN clicking "Edit Device" on a device row THEN the system SHALL navigate to the register table for that device
2. WHEN viewing the register management page THEN the system SHALL display the parent device name and gateway in the page header
3. WHEN on the register management page THEN the system SHALL provide navigation back to device list and gateway list

### Requirement 5

**User Story:** As a system administrator, I want to manage Modbus registers for each device, so that I can define data points for polling and monitoring.

#### Acceptance Criteria

1. WHEN accessing register management for a device THEN the system SHALL display a Filament table listing all registers for that device
2. WHEN viewing the register table THEN the system SHALL show technical label, function, register address, data type, and enabled status
3. WHEN clicking "Add Register" THEN the system SHALL open a form with fields: technical_label, function (enum), register_address, data_type (enum), byte_order (enum), scale (default 1), count, and enabled status
4. WHEN selecting function THEN the system SHALL provide options: 1=Coils, 2=Discrete Inputs, 3=Holding Registers, 4=Input Registers
5. WHEN selecting data_type THEN the system SHALL provide options: Int16, UInt16, Float32, Int32, and other standard Modbus types
6. WHEN selecting byte_order THEN the system SHALL provide options: Big Endian, Little Endian, Word Swap, Byte Swap
7. WHEN selecting data_type THEN the system SHALL automatically set appropriate default count value
8. WHEN clicking "Edit Register" THEN the system SHALL open the register form pre-populated with existing data
9. WHEN clicking "Delete Register" THEN the system SHALL prompt for confirmation and remove the register if confirmed
10. WHEN saving a register THEN the system SHALL validate register address is within valid Modbus range (0-65535)

### Requirement 6

**User Story:** As a system administrator, I want the system to maintain proper relationships between gateways, devices, and registers, so that data integrity is preserved across the hierarchy.

#### Acceptance Criteria

1. WHEN a gateway is deleted THEN the system SHALL cascade delete all associated devices and registers
2. WHEN a device is deleted THEN the system SHALL cascade delete all associated registers
3. WHEN viewing any level THEN the system SHALL display accurate counts of child items
4. WHEN creating devices THEN the system SHALL enforce foreign key relationship to gateway
5. WHEN creating registers THEN the system SHALL enforce foreign key relationship to device

### Requirement 7

**User Story:** As a system administrator, I want intuitive navigation between hierarchy levels, so that I can efficiently move between gateways, devices, and registers.

#### Acceptance Criteria

1. WHEN at any level THEN the system SHALL provide clear breadcrumb navigation
2. WHEN navigating between levels THEN the system SHALL maintain context of parent items
3. WHEN returning to a parent level THEN the system SHALL preserve any filters or sorting that were applied
4. WHEN on device or register pages THEN the system SHALL display parent item information prominently

### Requirement 8

**User Story:** As a system administrator, I want form validation and error handling, so that I can ensure data quality and receive clear feedback on issues.

#### Acceptance Criteria

1. WHEN submitting forms with invalid data THEN the system SHALL display specific validation error messages
2. WHEN IP addresses are invalid THEN the system SHALL show "Please enter a valid IP address" error
3. WHEN port numbers are out of range THEN the system SHALL show "Port must be between 1 and 65535" error
4. WHEN register addresses are invalid THEN the system SHALL show "Register address must be between 0 and 65535" error
5. WHEN required fields are empty THEN the system SHALL highlight missing fields and prevent submission
6. WHEN database operations fail THEN the system SHALL display user-friendly error messages