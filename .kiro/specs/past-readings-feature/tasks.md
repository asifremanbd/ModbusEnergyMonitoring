# Implementation Plan - Past Readings MVP

- [x] 1. Create Past Readings Filament page





  - Create `app/Filament/Pages/PastReadings.php` with navigation icon, label, and routing
  - Set navigation sort order to 4 (after Live Data which is 3)
  - Use 'heroicon-o-clock' icon and 'Past Readings' label
  - _Requirements: 1.1, 1.2, 1.3_

- [x] 2. Create Past Readings Livewire component





  - Create `app/Livewire/PastReadings.php` component class
  - Add properties for readings data, time range filter, and sorting
  - Implement mount() method to initialize with default settings (last 24 hours, newest first)
  - _Requirements: 2.1, 2.2, 3.1, 4.1_

- [x] 3. Implement data loading and filtering logic





  - Add loadReadings() method with database query using Reading model
  - Join with DataPoint and Gateway models to get required columns
  - Implement time range filtering for "Last 24 Hours", "Last 7 Days", "Last 30 Days"
  - Add server-side pagination with 50 records per page
  - _Requirements: 2.1, 2.2, 3.2, 3.3, 6.3_

- [x] 4. Add sorting functionality





  - Implement sortBy() method to handle timestamp column sorting
  - Add toggle between ascending and descending order
  - Default to newest first (descending) on page load
  - Update query to apply sorting on server side
  - _Requirements: 4.1, 4.2, 4.3_

- [x] 5. Create Blade template for Past Readings page





  - Create `resources/views/filament/pages/past-readings.blade.php`
  - Include the PastReadings Livewire component
  - Match styling and layout of existing Live Data page
  - _Requirements: 1.4, 2.1_

- [x] 6. Create Blade template for Livewire component





  - Create `resources/views/livewire/past-readings.blade.php`
  - Build responsive table with columns: Gateway, Group, Data Point, Value, Timestamp
  - Add time range filter buttons above the table
  - Include pagination controls below the table
  - Add sortable timestamp column header with visual indicators
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 3.2, 4.3_

- [x] 7. Implement CSV export functionality




  - Add exportCsv() method to PastReadings Livewire component
  - Generate CSV content with proper headers matching table columns
  - Create download response with appropriate filename and headers
  - Add "Export CSV" button to the page template
  - _Requirements: 5.1, 5.2, 5.3, 5.4_

- [x] 8. Add proper value formatting and display





  - Handle null/missing values by displaying "N/A" in Value column
  - Format timestamps in human-readable format with hover tooltips
  - Ensure numerical values are properly formatted
  - _Requirements: 2.2, 2.3, 2.4_

- [x] 9. Test and refine the implementation





  - Test navigation from sidebar to Past Readings page
  - Verify table displays historical data correctly
  - Test time range filtering functionality
  - Test sorting by timestamp (both directions)
  - Test CSV export with sample data
  - Verify pagination works correctly
  - _Requirements: 6.1, 6.2, 6.3, 6.4_