# Requirements Document - Past Readings MVP

## Introduction

The Past Readings MVP feature extends the existing Teltonika Gateway Monitor system by providing basic historical data viewing capabilities. This first version focuses on core functionality: viewing historical readings in a table format with basic time filtering and simple export capabilities. The MVP builds upon the existing Live Data functionality to provide essential historical data access without complex filtering or advanced features.

## Requirements

### Requirement 1

**User Story:** As an industrial operator, I want to access historical readings through a dedicated "Past Readings" menu item in the sidebar, so that I can easily navigate to historical data without confusion with live data monitoring.

#### Acceptance Criteria

1. WHEN the user views the sidebar navigation THEN the system SHALL display a "Past Readings" menu item positioned directly under the "Live Data" menu item
2. WHEN the "Past Readings" menu item is displayed THEN the system SHALL use a clock icon to represent historical data functionality
3. WHEN the user clicks the "Past Readings" menu item THEN the system SHALL navigate to a dedicated historical readings page that is separate from the Live Data page
4. WHEN the historical readings page loads THEN the system SHALL maintain consistent design language with the existing Live Data page

### Requirement 2

**User Story:** As a data analyst, I want to view historical readings in a simple table format, so that I can see past measurements from my gateways and data points.

#### Acceptance Criteria

1. WHEN the user accesses the Past Readings page THEN the system SHALL display a table with columns for Gateway, Group, Data Point, Value, and Timestamp
2. WHEN a reading has a valid value THEN the system SHALL display the numerical value in the Value column
3. WHEN a reading has no value or invalid data THEN the system SHALL display "N/A" in the Value column
4. WHEN the user views timestamps THEN the system SHALL display them in human-readable format (e.g., "Sep 18, 2025 2:30 PM")
5. WHEN the user hovers over a timestamp THEN the system SHALL show the exact timestamp in a tooltip
6. WHEN the table loads THEN the system SHALL show 50 results per page with pagination controls

### Requirement 3

**User Story:** As an energy monitoring specialist, I want to filter historical readings by basic time ranges, so that I can focus on recent data periods.

#### Acceptance Criteria

1. WHEN the Past Readings page loads THEN the system SHALL default to showing readings from the last 24 hours
2. WHEN the user accesses time range filtering THEN the system SHALL provide options for "Last 24 Hours", "Last 7 Days", and "Last 30 Days"
3. WHEN the user selects a time range THEN the system SHALL filter readings to show only data from that period
4. WHEN time filters are applied THEN the system SHALL perform server-side filtering for performance

### Requirement 4

**User Story:** As a data analyst, I want to sort historical readings by timestamp, so that I can see the most recent data first or view data chronologically.

#### Acceptance Criteria

1. WHEN the Past Readings page loads THEN the system SHALL sort results by timestamp with newest readings first
2. WHEN the user clicks on the Timestamp column header THEN the system SHALL toggle between ascending and descending sort order
3. WHEN the table is sorted THEN the system SHALL display a visual indicator (arrow) showing the current sort direction

### Requirement 5

**User Story:** As a reporting specialist, I want to export historical readings to CSV format, so that I can analyze the data in external tools.

#### Acceptance Criteria

1. WHEN the user views the Past Readings page THEN the system SHALL provide an "Export CSV" button
2. WHEN the user clicks "Export CSV" THEN the system SHALL generate a CSV file containing all currently displayed results
3. WHEN the CSV is generated THEN the system SHALL include appropriate column headers
4. WHEN export is complete THEN the system SHALL automatically download the file to the user's device

### Requirement 6

**User Story:** As a system administrator, I want the historical readings feature to perform efficiently, so that users can access data quickly without impacting system performance.

#### Acceptance Criteria

1. WHEN the database stores historical readings THEN the system SHALL maintain indexes on datapoint_id and created_at columns
2. WHEN filtering and sorting are performed THEN the system SHALL execute operations on the server side
3. WHEN the page loads THEN the system SHALL implement pagination to limit memory usage and response times
4. WHEN queries are executed THEN the system SHALL use efficient database queries with proper JOIN optimization