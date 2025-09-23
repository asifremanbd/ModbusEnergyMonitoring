# Design Document

## Overview

The Gateway UI Improvements feature enhances the existing Teltonika Gateway Monitor by modernizing the user interface, implementing a unified refresh system, and providing better visibility into system status and data quality. The design builds upon the existing Laravel + Filament v3 + Livewire architecture while introducing new components for global state management and enhanced status indicators.

## Architecture

### Component Structure

```
App/
├── Services/
│   ├── UiSettingsService.php          # Global refresh interval management
│   └── GatewayStatusService.php       # Enhanced status computation
├── Livewire/
│   ├── Components/
│   │   └── GlobalRefreshControl.php   # Global refresh interval selector
│   ├── LiveData.php                   # Enhanced with global refresh
│   └── PastReadings.php               # Enhanced with success/fail stats
├── Filament/
│   └── Resources/
│       └── GatewayResource.php        # Updated table actions and status
└── Models/
    └── Gateway.php                    # Enhanced status accessors
```

### Data Flow

1. **Global Refresh Control**: User selects refresh interval → stored in session → applied to all components
2. **Gateway Status**: Computed server-side based on polling health → cached → displayed in real-time
3. **Success/Fail Stats**: Aggregated from readings/poll logs → cached per time range → displayed in Past Readings

## Components and Interfaces

### 1. UiSettingsService

**Purpose**: Manage global UI settings including refresh intervals

```php
class UiSettingsService
{
    public function setRefreshInterval(int $seconds): void
    public function getRefreshInterval(): int
    public function getRefreshOptions(): array
    public function getLastUpdatedTimestamp(): ?Carbon
    public function updateLastRefresh(): void
}
```

**Key Features**:
- Session-based storage for refresh interval
- Default 5-second interval
- Options: Off (0), 2s, 5s, 10s, 30s
- Last updated timestamp tracking

### 2. GatewayStatusService

**Purpose**: Enhanced gateway status computation with degraded state detection

```php
class GatewayStatusService
{
    public function computeStatus(Gateway $gateway): string
    public function getStatusBadgeColor(string $status): string
    public function getRecentErrorRate(Gateway $gateway): float
    public function isWithinThreshold(Gateway $gateway, int $multiplier): bool
}
```

**Status Logic**:
- **Online** (green): `last_success_at < 2× poll_interval`
- **Degraded** (amber): `last_success_at between 2× and 5× poll_interval` OR `error_rate > 20%`
- **Offline** (red): `last_success_at > 5× poll_interval` OR no success in past N minutes
- **Paused** (gray): `is_active = false`

### 3. GlobalRefreshControl Component

**Purpose**: Livewire component for global refresh interval selection

```php
class GlobalRefreshControl extends Component
{
    public int $currentInterval;
    public string $lastUpdated;
    
    public function setInterval(int $seconds): void
    public function getFormattedLastUpdated(): string
}
```

**UI Features**:
- Dropdown with refresh options
- "Auto-refresh: 5s" display with timestamp
- "Updated 4s ago" relative time display
- Responsive design for mobile

### 4. Enhanced Gateway Resource

**Table Actions Modifications**:
```php
// Remove View action entirely
// Convert remaining actions to icon-only with tooltips

Action::make('test_connection')
    ->icon('heroicon-o-radio')
    ->tooltip('Test connection')
    ->label(null)
    ->button()

Action::make('pause')
    ->icon('heroicon-o-pause')
    ->tooltip('Pause polling')
    ->label(null)
    ->button()
    ->visible(fn (Gateway $record): bool => $record->is_active)

Action::make('resume')
    ->icon('heroicon-o-play')
    ->tooltip('Resume polling')
    ->label(null)
    ->button()
    ->visible(fn (Gateway $record): bool => !$record->is_active)

Tables\Actions\EditAction::make()
    ->icon('heroicon-o-pencil')
    ->tooltip('Edit gateway')
    ->label(null)
    ->button()

Tables\Actions\DeleteAction::make()
    ->icon('heroicon-o-trash')
    ->tooltip('Delete gateway')
    ->label(null)
    ->button()
```

**Enhanced Status Column**:
```php
TextColumn::make('status')
    ->getStateUsing(function (Gateway $record): string {
        return app(GatewayStatusService::class)->computeStatus($record);
    })
    ->badge()
    ->color(function (string $state): string {
        return app(GatewayStatusService::class)->getStatusBadgeColor($state);
    })
```

### 5. Enhanced Live Data Component

**Global Refresh Integration**:
```php
// In Blade template
<div wire:poll.{{ $this->getRefreshIntervalMs() }}ms.visible>
    <!-- Live data content -->
</div>

// In component
public function getRefreshIntervalMs(): int
{
    $interval = app(UiSettingsService::class)->getRefreshInterval();
    return $interval > 0 ? $interval * 1000 : 0; // Convert to ms, 0 = no polling
}
```

### 6. Enhanced Past Readings Component

**Success/Fail Statistics**:
```php
class PastReadings extends Component
{
    public array $successFailStats = [];
    
    public function loadSuccessFailStats(): void
    {
        $timeFilter = $this->getTimeFilter();
        
        // Aggregate success/fail counts from readings or poll logs
        $stats = Reading::join('data_points', 'readings.data_point_id', '=', 'data_points.id')
            ->join('gateways', 'data_points.gateway_id', '=', 'gateways.id')
            ->where('readings.read_at', '>=', $timeFilter)
            ->selectRaw('
                COUNT(CASE WHEN readings.quality = "good" THEN 1 END) as success_count,
                COUNT(CASE WHEN readings.quality != "good" THEN 1 END) as fail_count
            ')
            ->first();
            
        $this->successFailStats = [
            'success' => $stats->success_count ?? 0,
            'fail' => $stats->fail_count ?? 0,
        ];
    }
}
```

## Data Models

### Gateway Model Enhancements

**New Accessors**:
```php
// Enhanced status with degraded state
public function getEnhancedStatusAttribute(): string
{
    return app(GatewayStatusService::class)->computeStatus($this);
}

// Recent error rate calculation
public function getRecentErrorRateAttribute(): float
{
    return app(GatewayStatusService::class)->getRecentErrorRate($this);
}
```

**Database Considerations**:
- Existing `last_seen_at`, `success_count`, `failure_count` columns sufficient
- Consider adding `last_success_at` column for more precise status calculation
- Index on `last_seen_at` and `last_success_at` for performance

### Session Storage Schema

**UI Settings in Session**:
```php
session([
    'ui_settings' => [
        'refresh_interval' => 5, // seconds
        'last_updated' => '2025-09-18 14:30:00',
    ]
]);
```

## Error Handling

### Status Computation Fallbacks

1. **Missing Timestamps**: Default to 'unknown' status with appropriate messaging
2. **Database Errors**: Graceful degradation to basic online/offline status
3. **Service Unavailability**: Cache last known status for brief periods

### Refresh Control Error Handling

1. **Invalid Intervals**: Validate and fallback to default 5s
2. **Session Issues**: Use in-memory defaults for current session
3. **Component Failures**: Disable auto-refresh but maintain manual refresh

### Statistics Computation Errors

1. **Query Failures**: Display "Stats unavailable" with retry option
2. **Large Dataset Timeouts**: Implement query optimization and caching
3. **Missing Data**: Show appropriate empty states

## Testing Strategy

### Unit Tests

1. **UiSettingsService**: Test interval validation, session storage, timestamp handling
2. **GatewayStatusService**: Test status computation logic, error rate calculations
3. **Gateway Model**: Test enhanced accessors and status logic

### Integration Tests

1. **Global Refresh Control**: Test interval changes propagate to all components
2. **Gateway Resource**: Test icon-only actions maintain functionality
3. **Live Data Integration**: Test refresh interval changes affect polling
4. **Past Readings Stats**: Test success/fail aggregation accuracy

### Browser Tests

1. **Accessibility**: Test keyboard navigation, screen reader compatibility
2. **Responsive Design**: Test mobile layouts for new components
3. **Tooltip Functionality**: Test hover and focus states for icon actions
4. **Real-time Updates**: Test status badge updates with global refresh

### Performance Tests

1. **Status Computation**: Test performance with large numbers of gateways
2. **Statistics Queries**: Test aggregation performance with historical data
3. **Refresh Polling**: Test system load with multiple concurrent users
4. **Caching Effectiveness**: Test cache hit rates for computed values

## Implementation Considerations

### Filament v3 Compatibility

- Use `->tooltip()` method for action tooltips
- Leverage `->button()` styling for minimal icon actions
- Maintain existing slideOver and modal patterns
- Ensure compatibility with existing table filters and bulk actions

### Livewire Integration

- Use `wire:poll` with dynamic intervals
- Implement proper component lifecycle management
- Handle browser visibility API for efficient polling
- Maintain existing event broadcasting integration

### Performance Optimization

- Cache gateway status computations for refresh interval duration
- Implement efficient database queries for statistics
- Use database indexes for timestamp-based filtering
- Consider Redis caching for high-traffic scenarios

### Accessibility Compliance

- Maintain WCAG AA compliance for all new UI elements
- Ensure proper ARIA labels for icon-only actions
- Implement keyboard navigation for all interactive elements
- Provide alternative text for status indicators

### Mobile Responsiveness

- Ensure global refresh control works on mobile devices
- Maintain table responsiveness with icon-only actions
- Test touch interactions for all new UI elements
- Optimize for various screen sizes and orientations