# Requirements Document

## Introduction

The Teltonika Gateway Monitor is a comprehensive industrial IoT application designed to monitor and manage Modbus-enabled Teltonika energy meters through a web-based dashboard. The system provides real-time data collection, visualization, alerting, and administrative capabilities with a focus on energy monitoring use cases. The application features a modern, responsive interface inspired by RayleighConnect design principles, emphasizing usability for industrial operators and administrators.

## Requirements

### Requirement 1

**User Story:** As an industrial operator, I want to view a comprehensive dashboard showing the status of all my gateways and key performance indicators, so that I can quickly assess the health of my energy monitoring infrastructure.

#### Acceptance Criteria

1. WHEN the user accesses the dashboard THEN the system SHALL display KPI tiles showing Online Gateways count, Poll Success percentage, and Average Poll Latency
2. WHEN the dashboard loads THEN the system SHALL display a fleet status strip showing each gateway as a small card with name, IP, last seen timestamp, and sparkline chart
3. WHEN new events occur THEN the system SHALL display a time-ordered list of recent events including gateway offline and configuration changed events
4. WHEN the dashboard is viewed on mobile devices THEN the system SHALL stack dashboard tiles vertically for optimal mobile viewing

### Requirement 2

**User Story:** As a system administrator, I want to manage gateway connections and configure Modbus polling parameters, so that I can ensure reliable data collection from my Teltonika devices.

#### Acceptance Criteria

1. WHEN the user views the gateways index THEN the system SHALL display a table with columns for Name, IP:Port, Unit ID, Poll Interval, Last Seen, Success/Fail counters, and Status
2. WHEN the user clicks on a gateway row THEN the system SHALL provide actions for View, Pause, Restart Polling, Edit, and Delete
3. WHEN the user clicks "Add Gateway" THEN the system SHALL open a wizard with steps for Connect, Map Points, and Review & Start
4. WHEN the user tests a connection THEN the system SHALL display connection status with latency measurement and probe register value
5. WHEN the user configures a gateway THEN the system SHALL validate that Register values are between 1-65535
6. WHEN the user saves gateway configuration THEN the system SHALL display a success toast with undo option for dangerous actions

### Requirement 3

**User Story:** As an energy monitoring specialist, I want to map Modbus registers to meaningful data points with proper data types and scaling, so that I can collect accurate energy measurements from my meters.

#### Acceptance Criteria

1. WHEN the user maps points during gateway setup THEN the system SHALL provide a starter template for "Teltonika Data Source (float32, 2 regs, word-swapped)"
2. WHEN the user adds data points THEN the system SHALL allow configuration of Group, Label, Function (Input/Holding), Register, Count, Data Type (byte order), Scale, and Enable status
3. WHEN the user previews a point THEN the system SHALL perform a single read to verify the register configuration and display the result
4. WHEN the user clones a group THEN the system SHALL copy Meter_1 configuration to Meter_2 with automatic label and register pattern adjustment
5. WHEN the user enables bulk operations THEN the system SHALL support Enable/Disable, Duplicate to group, and Export CSV actions
6. WHEN the user configures default settings THEN the system SHALL use Port 502, Unit ID 1, Poll interval 10s, Function 4 (Input), Count 2, Type Float32 word-swapped

### Requirement 4

**User Story:** As an operator, I want to view live data readings from all configured points with real-time updates, so that I can monitor current energy consumption and system performance.

#### Acceptance Criteria

1. WHEN the user accesses live readings THEN the system SHALL display a table with sticky headers showing current values and mini trend charts for the last 10 readings
2. WHEN new data is polled THEN the system SHALL auto-refresh the live values with status dots indicating data quality
3. WHEN the user applies filters THEN the system SHALL filter by Gateway, Group, and Tag type using filter chips
4. WHEN the user toggles density THEN the system SHALL switch between Comfortable and Compact table views
5. WHEN data is unavailable THEN the system SHALL display appropriate status indicators ("Up", "Down", "Unknown") with dot and label format

### Requirement 5

**User Story:** As any system user, I want an accessible and responsive interface that works across devices and follows modern design principles, so that I can effectively use the system regardless of my device or accessibility needs.

#### Acceptance Criteria

1. WHEN the interface is displayed THEN the system SHALL use WCAG AA compliant colors, focus rings, and keyboard navigable tables
2. WHEN viewed on mobile devices THEN the system SHALL provide responsive layouts with dashboard tiles stacking and tables scrolling with sticky column headers
3. WHEN the user encounters empty states THEN the system SHALL display helpful messages like "No points yet. Add your first measurement or import from template"
4. WHEN errors occur THEN the system SHALL provide clear diagnostic information for gateway offline, value decode mismatch, and high failure rate scenarios
5. WHEN the user navigates THEN the system SHALL use left rail navigation with deep navy background, white content areas, and blue accent colors
6. WHEN forms are displayed THEN the system SHALL use context drawers for edit/inspect actions instead of full page navigation