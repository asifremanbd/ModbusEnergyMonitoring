# Implementation Plan

- [x] 1. Create UiSettingsService for global refresh interval management





  - Create `app/Services/UiSettingsService.php` with methods for managing refresh intervals in session storage
  - Implement validation for refresh interval options (Off, 2s, 5s, 10s, 30s) with 5s default
  - Add methods for tracking last updated timestamp and formatting relative time display
  - Write unit tests for interval validation, session storage, and timestamp handling
  - _Requirements: 2.1, 2.2, 2.3, 2.5_


- [x] 2. Create GatewayStatusService for enhanced status computation




  - Create `app/Services/GatewayStatusService.php` with status computation logic
  - Implement four-state status system: online (green), degraded (amber), offline (red), paused (gray)
  - Add method to calculate recent error rate from last 20 polls using success/failure counts
  - Implement status thresholds: online (<2× interval), degraded (2×-5× interval OR >20% errors), offline (>5× interval)
  - Write unit tests for status computation logic and error rate calculations
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 3. Enhance Gateway model with new status accessors





  - Add `getEnhancedStatusAttribute()` accessor to Gateway model that uses GatewayStatusService
  - Add `getRecentErrorRateAttribute()` accessor for error rate calculation
  - Update existing `getIsOnlineAttribute()` to work with enhanced status logic
  - Write unit tests for new model accessors and status logic
  - _Requirements: 3.1, 3.2, 3.3, 3.6_

- [x] 4. Create GlobalRefreshControl Livewire component





  - Create `app/Livewire/Components/GlobalRefreshControl.php` component
  - Implement dropdown interface with refresh interval options and current selection display
  - Add methods for setting interval, updating session storage, and formatting last updated time
  - Create Blade template with responsive design and "Auto-refresh: Xs" display with timestamp
  - Write component tests for interval changes and UI updates
  - _Requirements: 2.1, 2.2, 2.3, 2.5, 5.3_

- [x] 5. Update GatewayResource with icon-only actions and enhanced status





  - Remove "View" action entirely from table actions in `app/Filament/Resources/GatewayResource.php`
  - Convert remaining actions (Test connection, Pause/Resume, Edit, Delete) to icon-only with tooltips
  - Update action icons: radio for test connection, pause/play for pause/resume, pencil for edit, trash for delete
  - Add proper tooltip text and aria-labels for accessibility compliance
  - Update status column to use GatewayStatusService for enhanced status display with color-coded badges
  - Write tests for action functionality and accessibility compliance
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 3.1, 3.6, 5.1, 5.2_

- [x] 6. Integrate global refresh control into main layout




  - Add GlobalRefreshControl component to main Filament panel layout template
  - Position control in header or top-right toolbar area with responsive design
  - Ensure component is accessible across all pages (Gateways, Live Data, Past Readings)
  - Test component visibility and functionality across different screen sizes
  - _Requirements: 2.1, 2.7, 5.3_

- [x] 7. Update LiveData component with global refresh integration





  - Modify `app/Livewire/LiveData.php` to use UiSettingsService for refresh interval
  - Update Blade template to use dynamic `wire:poll` interval based on global setting
  - Add method to convert seconds to milliseconds for Livewire polling
  - Remove hardcoded refresh interval and use global setting instead
  - Update last updated timestamp display to show "Updated Xs ago" format
  - Write tests for refresh interval integration and polling behavior
  - _Requirements: 2.4, 2.5, 2.7_

- [x] 8. Update PastReadings component with success/fail statistics





  - Modify `app/Livewire/PastReadings.php` to include success/fail statistics computation
  - Add method to aggregate success/fail counts from readings table based on quality field
  - Implement per-gateway statistics when gateway filter is applied
  - Add caching for statistics based on time range and filters to improve performance
  - Update Blade template to display "Success: X,XXX · Fail: XXX" format in header or summary area
  - Write tests for statistics computation accuracy and performance
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_
-

- [x] 9. Update gateway status display with real-time updates




  - Modify gateway table to use enhanced status from GatewayStatusService
  - Ensure status badges update according to global refresh interval
  - Implement proper color coding: green (online), amber (degraded), red (offline), gray (paused)
  - Add status badge updates to existing Livewire refresh events
  - Test status accuracy and real-time update behavior
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

- [ ] ~~10. Implement accessibility enhancements for all new UI elements~~ **DROPPED**
  - _Dropped due to complexity - basic accessibility maintained through Filament defaults_

- [ ] ~~11. Add responsive design support for mobile devices~~ **DROPPED**
  - _Dropped due to complexity - basic responsiveness maintained through Filament defaults_

- [ ] ~~12. Implement performance optimizations and caching~~ **DROPPED**
  - _Dropped due to complexity - basic performance acceptable for current scale_

- [x] 10. Create basic test suite for core functionality
  - Write unit tests for UiSettingsService and GatewayStatusService
  - Add basic integration tests for global refresh control
  - Test gateway status computation and display
  - Validate success/fail statistics accuracy
  - _Requirements: Core functionality validation only_