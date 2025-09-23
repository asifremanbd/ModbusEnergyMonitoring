# Requirements Document

## Introduction

The Gateway UI Improvements feature enhances the existing Teltonika Gateway Monitor system by modernizing the Gateways table interface, implementing a unified refresh system across all pages, and providing better status visibility. This feature focuses on improving user experience through cleaner table actions, consistent auto-refresh behavior, real-time gateway status indicators, and enhanced data visibility in the Past Readings section.

## Requirements

### Requirement 1

**User Story:** As a system administrator, I want a cleaner Gateways table interface with icon-only actions and tooltips, so that I can manage gateways more efficiently with less visual clutter.

#### Acceptance Criteria

1. WHEN the user views the Gateways table THEN the system SHALL remove the "View" action entirely from the row actions
2. WHEN the user views row actions THEN the system SHALL display only "Test connection", "Pause/Resume", "Edit", and "Delete" actions
3. WHEN row actions are displayed THEN the system SHALL show them as icon-only buttons without text labels
4. WHEN the user hovers over an action icon THEN the system SHALL display a tooltip with the action name (e.g., "Test connection", "Pause", "Resume", "Edit", "Delete")
5. WHEN the user focuses on an action icon with keyboard navigation THEN the system SHALL display appropriate focus rings and aria-labels for accessibility
6. WHEN action icons are displayed THEN the system SHALL use sensible icons: radio/tower for "Test connection", pause/play for "Pause/Resume", pencil for "Edit", trash for "Delete"

### Requirement 2

**User Story:** As an industrial operator, I want a unified auto-refresh system across all pages, so that I can maintain consistent data freshness without managing different refresh settings on each page.

#### Acceptance Criteria

1. WHEN the user accesses any page THEN the system SHALL display a global refresh interval control in the header or top-right toolbar
2. WHEN the refresh control is displayed THEN the system SHALL provide options for "Off", "2s", "5s", "10s", and "30s" intervals
3. WHEN the user selects a refresh interval THEN the system SHALL persist the selection in the user session
4. WHEN the refresh interval is active THEN the system SHALL apply it to Live Data auto-refresh using Livewire wire:poll
5. WHEN the refresh interval is active THEN the system SHALL show "Auto-refresh: {interval}" text with last updated timestamp (e.g., "Updated 4s ago")
6. WHEN no refresh interval is selected THEN the system SHALL default to 5 seconds
7. WHEN the user is on Past Readings page THEN the system SHALL not continuously poll but SHALL respect the interval for manual refresh button behavior

### Requirement 3

**User Story:** As a monitoring specialist, I want to see real-time gateway status indicators in the Gateways table, so that I can quickly identify which gateways are online, degraded, or offline.

#### Acceptance Criteria

1. WHEN polling is enabled for a gateway THEN the system SHALL compute and display a status badge in the Gateways table
2. WHEN a gateway's last successful poll is less than 2× the gateway's poll interval THEN the system SHALL display an "online" status with green badge
3. WHEN a gateway's last success is between 2× and 5× the poll interval OR recent errors exceed 20% in the last 20 polls THEN the system SHALL display a "degraded" status with amber badge
4. WHEN a gateway's last success is greater than 5× the poll interval OR no success in past N minutes THEN the system SHALL display an "offline" status with red badge
5. WHEN polling is disabled for a gateway THEN the system SHALL display a "paused" status with gray badge
6. WHEN the global refresh interval is active THEN the system SHALL update gateway status badges according to the same refresh cadence

### Requirement 4

**User Story:** As a data analyst, I want to see Success/Fail statistics in the Past Readings page, so that I can understand data quality for the current time range and filters.

#### Acceptance Criteria

1. WHEN the user views the Past Readings page THEN the system SHALL display a summary showing Success/Fail totals for the current time range and filters
2. WHEN the summary is displayed THEN the system SHALL show format like "Success: 4,068 · Fail: 706"
3. WHEN a gateway filter is applied THEN the system SHALL show per-gateway Success/Fail statistics for the selected gateway
4. WHEN Success/Fail counts are computed THEN the system SHALL calculate them server-side from poll/job logs or reading quality flags
5. WHEN the time range or filters change THEN the system SHALL update the Success/Fail counts accordingly
6. WHEN possible THEN the system SHALL cache Success/Fail counts for the selected refresh interval to improve performance

### Requirement 5

**User Story:** As any system user, I want all UI improvements to maintain accessibility and responsiveness, so that I can use the enhanced features effectively across different devices and interaction methods.

#### Acceptance Criteria

1. WHEN icon-only actions are displayed THEN the system SHALL maintain WCAG AA compliance with proper focus indicators and keyboard navigation
2. WHEN tooltips are shown THEN the system SHALL include both hover tooltips and title attributes for screen readers
3. WHEN the global refresh control is displayed THEN the system SHALL be responsive and accessible on mobile devices
4. WHEN status badges are shown THEN the system SHALL use appropriate color contrast and include text labels for accessibility
5. WHEN Success/Fail statistics are displayed THEN the system SHALL be readable and properly formatted on all screen sizes
6. WHEN any new UI elements are added THEN the system SHALL maintain consistency with the existing Filament v3 design system