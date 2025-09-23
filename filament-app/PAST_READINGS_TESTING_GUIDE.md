# Past Readings Feature - Manual Testing Guide

This guide provides step-by-step instructions for manually testing the Past Readings feature to verify it meets all requirements.

## Prerequisites

1. Ensure the Laravel application is running: `php artisan serve`
2. Access the admin panel (typically at `http://localhost:8000/admin`)
3. Ensure you have test data in the database (gateways, data points, and readings)

## Test Cases

### 1. Navigation Testing (Requirements 1.1, 1.2, 1.3)

**Test 1.1: Menu Item Visibility**
- [ ] Open the admin panel sidebar
- [ ] Verify "Past Readings" menu item is visible
- [ ] Verify it appears after "Live Data" menu item
- [ ] Verify it uses a clock icon (heroicon-o-clock)

**Test 1.2: Navigation Functionality**
- [ ] Click on "Past Readings" menu item
- [ ] Verify it navigates to `/admin/past-readings`
- [ ] Verify the page loads without errors
- [ ] Verify the page title shows "Past Readings"

### 2. Data Display Testing (Requirements 2.1, 2.2, 2.3, 2.4, 2.5)

**Test 2.1: Table Structure**
- [ ] Verify table has 5 columns: Gateway, Group, Data Point, Value, Timestamp
- [ ] Verify column headers are properly labeled
- [ ] Verify table is responsive on different screen sizes

**Test 2.2: Value Display**
- [ ] Verify numerical values are displayed with 2 decimal places (e.g., "230.50")
- [ ] Verify null/invalid values display as "N/A"
- [ ] Verify "N/A" values are styled differently (grayed out)

**Test 2.3: Timestamp Display**
- [ ] Verify timestamps are in human-readable format (e.g., "Sep 18, 2025 2:30 PM")
- [ ] Verify timestamps are properly formatted and consistent

**Test 2.4: Timestamp Tooltips**
- [ ] Hover over timestamp values
- [ ] Verify tooltip shows full timestamp with timezone
- [ ] Verify tooltip appears and disappears correctly

**Test 2.5: Pagination**
- [ ] Verify table shows 50 records per page
- [ ] Verify pagination controls appear when there are more than 50 records
- [ ] Verify "Showing X to Y of Z results" text is accurate
- [ ] Test navigation between pages using Previous/Next buttons
- [ ] Test direct page navigation using page numbers

### 3. Time Range Filtering (Requirements 3.1, 3.2, 3.3)

**Test 3.1: Default Time Range**
- [ ] Verify page loads with "Last 24 Hours" selected by default
- [ ] Verify only readings from the last 24 hours are displayed

**Test 3.2: Time Range Options**
- [ ] Click "Last 7 Days" button
- [ ] Verify button becomes active/highlighted
- [ ] Verify more readings appear (if available)
- [ ] Click "Last 30 Days" button
- [ ] Verify button becomes active/highlighted
- [ ] Verify even more readings appear (if available)
- [ ] Click "Last 24 Hours" to return to default
- [ ] Verify filtering works correctly

**Test 3.3: Server-side Filtering Performance**
- [ ] Monitor network requests when changing time ranges
- [ ] Verify filtering happens on server-side (not client-side)
- [ ] Verify page loads quickly even with large datasets

### 4. Sorting Functionality (Requirements 4.1, 4.2, 4.3)

**Test 4.1: Default Sorting**
- [ ] Verify readings are sorted by timestamp with newest first (descending)
- [ ] Verify the most recent readings appear at the top

**Test 4.2: Sort Toggle**
- [ ] Click on the "Timestamp" column header
- [ ] Verify sort changes to ascending (oldest first)
- [ ] Click again on "Timestamp" column header
- [ ] Verify sort changes back to descending (newest first)

**Test 4.3: Visual Sort Indicators**
- [ ] Verify down arrow (↓) appears when sorting descending
- [ ] Verify up arrow (↑) appears when sorting ascending
- [ ] Verify arrows are clearly visible and properly positioned

### 5. CSV Export Functionality (Requirements 5.1, 5.2, 5.3, 5.4)

**Test 5.1: Export Button**
- [ ] Verify "Export CSV" button is visible in the page header
- [ ] Verify button has appropriate styling and icon

**Test 5.2: CSV Generation**
- [ ] Click "Export CSV" button
- [ ] Verify CSV file downloads automatically
- [ ] Verify download includes all currently filtered results (not just current page)

**Test 5.3: CSV Content**
- [ ] Open the downloaded CSV file
- [ ] Verify headers: "Gateway,Group,Data Point,Value,Timestamp"
- [ ] Verify data rows contain correct information
- [ ] Verify values are properly formatted (2 decimals for numbers, "N/A" for nulls)
- [ ] Verify timestamps are in readable format

**Test 5.4: File Download**
- [ ] Verify filename includes timestamp (e.g., "past_readings_2025-09-18_14-30-15.csv")
- [ ] Verify file downloads to default download location
- [ ] Verify file can be opened in Excel or similar applications

### 6. Performance Testing (Requirements 6.1, 6.2, 6.3, 6.4)

**Test 6.1: Database Performance**
- [ ] Monitor database queries using Laravel Debugbar or similar
- [ ] Verify queries use appropriate indexes
- [ ] Verify query execution times are reasonable

**Test 6.2: Server-side Operations**
- [ ] Verify all filtering and sorting happens on server-side
- [ ] Verify pagination is server-side (not loading all data at once)

**Test 6.3: Pagination Performance**
- [ ] Test with large datasets (1000+ readings)
- [ ] Verify page loads remain fast
- [ ] Verify memory usage is reasonable

**Test 6.4: Query Efficiency**
- [ ] Verify JOIN queries are optimized
- [ ] Verify no N+1 query problems
- [ ] Verify appropriate use of eager loading

### 7. Edge Cases and Error Handling

**Test 7.1: Empty State**
- [ ] Filter to a time range with no data
- [ ] Verify "No past readings found" message appears
- [ ] Verify helpful text suggests trying different time periods

**Test 7.2: Large Datasets**
- [ ] Test with 1000+ readings
- [ ] Verify pagination works correctly
- [ ] Verify performance remains acceptable

**Test 7.3: Network Issues**
- [ ] Test with slow network connection
- [ ] Verify loading states are shown appropriately
- [ ] Verify graceful handling of timeouts

**Test 7.4: Browser Compatibility**
- [ ] Test in Chrome, Firefox, Safari, Edge
- [ ] Verify responsive design works on mobile devices
- [ ] Verify accessibility features work with screen readers

## Acceptance Criteria Verification

After completing all tests, verify the following acceptance criteria are met:

### Navigation (Requirement 1)
- [x] "Past Readings" menu item appears in sidebar after "Live Data"
- [x] Uses clock icon to represent historical data
- [x] Navigates to dedicated page separate from Live Data
- [x] Maintains consistent design with existing pages

### Data Display (Requirement 2)
- [x] Table shows Gateway, Group, Data Point, Value, Timestamp columns
- [x] Numerical values display with proper formatting
- [x] Null/invalid values show as "N/A"
- [x] Timestamps in human-readable format with tooltips
- [x] 50 results per page with pagination

### Time Filtering (Requirement 3)
- [x] Defaults to last 24 hours
- [x] Provides 24h, 7d, 30d options
- [x] Server-side filtering for performance

### Sorting (Requirement 4)
- [x] Defaults to newest first
- [x] Clickable timestamp header toggles sort
- [x] Visual indicators show sort direction

### CSV Export (Requirement 5)
- [x] Export CSV button available
- [x] Downloads all current results
- [x] Proper CSV headers and formatting
- [x] Automatic file download

### Performance (Requirement 6)
- [x] Uses database indexes efficiently
- [x] Server-side operations
- [x] Pagination for performance
- [x] Optimized database queries

## Test Results

**Date:** ___________  
**Tester:** ___________  
**Environment:** ___________

**Overall Result:** [ ] PASS [ ] FAIL

**Notes:**
_________________________________
_________________________________
_________________________________

**Issues Found:**
_________________________________
_________________________________
_________________________________

**Recommendations:**
_________________________________
_________________________________
_________________________________