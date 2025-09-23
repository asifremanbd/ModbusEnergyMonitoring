# Requirements Document

## Introduction

The polling system shows gateways as "polling enabled" but no background jobs are running, resulting in no live or past data collection. The system needs to be fixed to ensure reliable polling that runs only when `polling_enabled = true`, respects `poll_interval_seconds`, prevents duplicates, and maintains persistent workers.

## Requirements

### Requirement 1

**User Story:** As a system administrator, I want polling to work when enabled, so that data is collected and visible in the admin interface.

#### Acceptance Criteria

1. WHEN a gateway has `polling_enabled = true` THEN the system SHALL run background polling jobs
2. WHEN a gateway has `polling_enabled = false` THEN the system SHALL NOT run polling jobs for that gateway
3. WHEN polling runs THEN the system SHALL collect data at the configured `poll_interval_seconds` interval
4. WHEN data is collected THEN it SHALL appear in both live data and past readings interfaces

### Requirement 2

**User Story:** As a system administrator, I want to prevent duplicate readings, so that data integrity is maintained.

#### Acceptance Criteria

1. WHEN polling collects data THEN the system SHALL prevent duplicate entries with the same timestamp and gateway
2. WHEN duplicates are detected THEN the system SHALL skip the duplicate and log the event
3. WHEN multiple processes poll simultaneously THEN the system SHALL handle race conditions properly

### Requirement 3

**User Story:** As a system administrator, I want persistent polling workers, so that polling never stops unexpectedly.

#### Acceptance Criteria

1. WHEN polling workers start THEN they SHALL maintain "ACTIVE" status continuously
2. IF a worker fails THEN the system SHALL automatically restart it
3. WHEN the system starts THEN workers SHALL automatically start for all enabled gateways
4. WHEN checking status THEN the system SHALL show accurate worker information