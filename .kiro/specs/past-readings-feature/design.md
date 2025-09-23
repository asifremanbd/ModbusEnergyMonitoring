# Design Document - Past Readings MVP

## Overview

The Past Readings MVP feature extends the existing Teltonika Gateway Monitor system by providing basic historical data viewing capabilities. This feature follows the established Filament Admin Panel architecture with a dedicated page component and Livewire component for real-time interactions. The design leverages the existing Reading, DataPoint, and Gateway models while adding minimal new components to achieve the core functionality.

### Key Design Principles
- **Consistency**: Maintain design consistency with the existing Live Data page
- **Performance**: Server-side filtering and pagination for efficient data handling
- **Simplicity**: Focus on core functionality for MVP without complex features
- **Extensibility**: Design foundation that can be extended with advanced features later

## Architecture

The Past Readings MVP is a simple extension to the existing system: a Filament page with a Livewire component that queries existing models and renders a table with time filters and CSV export.

### Component Integration
- **Navigation**: New menu item positioned after Live Data with clock icon
- **Models**: Reuses existing Reading, DataPoint, and Gateway models
- **Database**: Leverages existing database schema and indexes
- **UI Framework**: Uses Filament's styling and Livewire for interactivity

## Components and Interfaces

### Frontend Components

#### PastReadings Filament Page
A dedicated Filament page component that provides navigation and basic page structure.

```php
class PastReadings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Past Readings';
    protected static ?int $navigationSort = 4; // After Live Data (3)
    protected static string $view = 'filament.pages.past-readings';
    protected static ?string $slug = 'past-readings';
}
```

**Design Rationale**: Follows the exact same pattern as the existing LiveData page to maintain consistency and leverage Filament's navigation system.

#### PastReadings Livewire Component
The main interactive component that handles data loading, filtering, sorting, and export functionality.

```php
class PastReadings extends Component
{
    // Properties
    public $readings = [];
    public $timeRange = 'last_24_hours';
    public $sortField = 'read_at';
    public $sortDirection = 'desc';
    
    // Methods
    public function mount()
    public function loadReadings()
    public function setTimeRange($range)
    public function sortBy($field)
    public function exportCsv()
    public function render()
}
```

**Key Features**:
- Server-side pagination (50 records per page)
- Time range filtering with preset options
- Sortable timestamp column
- CSV export functionality
- Consistent styling with Live Data page

### Backend Components

#### CSV Export Logic
For the MVP, CSV export logic will be included directly in the Livewire component to keep things simple. The component will generate CSV content and trigger a download response.

### Data Flow

1. **Page Load**: User clicks "Past Readings" in navigation
2. **Component Mount**: PastReadings Livewire component initializes with default filters
3. **Data Query**: Server-side query with time range filter and pagination
4. **Rendering**: Table displays with readings data and pagination controls
5. **Interactions**: User can change time range, sort, or export data
6. **Real-time Updates**: Component refreshes data based on user interactions

## Data Models

### Existing Models Usage

The feature leverages existing models without modifications:

#### Reading Model
```php
// Existing model with all necessary relationships and scopes
class Reading extends Model
{
    // Relationships
    public function dataPoint(): BelongsTo
    public function gateway(): BelongsTo (through dataPoint)
    
    // Scopes
    public function scopeRecent($query, int $minutes = 60)
    public function scopeForDataPoint($query, int $dataPointId)
    
    // Attributes
    public function getDisplayValueAttribute(): string
}
```

#### DataPoint Model
```php
// Existing model with gateway relationship
class DataPoint extends Model
{
    public function gateway(): BelongsTo
    public function readings(): HasMany
}
```

#### Gateway Model
```php
// Existing model with data points relationship
class Gateway extends Model
{
    public function dataPoints(): HasMany
    public function readings(): HasMany (through dataPoints)
}
```

### Database Queries

#### Main Readings Query
```sql
SELECT 
    r.id,
    r.scaled_value,
    r.read_at,
    r.quality,
    dp.label as data_point_label,
    dp.group_name,
    g.name as gateway_name
FROM readings r
INNER JOIN data_points dp ON r.data_point_id = dp.id
INNER JOIN gateways g ON dp.gateway_id = g.id
WHERE r.read_at >= ?
ORDER BY r.read_at DESC
LIMIT 50 OFFSET ?
```

**Design Rationale**: Efficient JOIN query leveraging existing indexes on `data_point_id` and `read_at` columns for optimal performance.

## User Interface Design

### Page Layout

```
┌─────────────────────────────────────────────────────────────┐
│ Past Readings                                    [Export CSV] │
├─────────────────────────────────────────────────────────────┤
│ Time Range: [Last 24 Hours ▼] [Last 7 Days] [Last 30 Days]  │
├─────────────────────────────────────────────────────────────┤
│ Gateway    │ Group     │ Data Point │ Value    │ Timestamp ↓ │
├─────────────────────────────────────────────────────────────┤
│ Gateway1   │ Meter_1   │ Voltage L1 │ 230.5    │ Sep 18 2:30 │
│ Gateway1   │ Meter_1   │ Current L1 │ 15.2     │ Sep 18 2:30 │
│ Gateway2   │ Meter_2   │ Power      │ N/A      │ Sep 18 2:29 │
├─────────────────────────────────────────────────────────────┤
│                                    [← Previous] [Next →]     │
└─────────────────────────────────────────────────────────────┘
```

### Responsive Design
Filament tables are already responsive and will handle different screen sizes appropriately.

### Accessibility Features
Use Filament's default accessibility features which already provide WCAG compliance.

## Error Handling

### Basic Error Handling
- Show error messages for failed database queries
- Display "No data found" message when filters return empty results
- Handle CSV export failures with user-friendly error messages

## Performance Considerations

Use indexed database queries and server-side pagination (50 rows per page) to ensure good performance. Laravel and Filament already handle most optimization concerns.

## Testing Strategy

### Basic Testing
- Unit test Livewire component logic and basic CSV export functionality
- Test page navigation and basic table functionality
- Integration and performance testing can be added in future iterations