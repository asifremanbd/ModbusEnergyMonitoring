# Dashboard Implementation

## Overview

The dashboard provides a comprehensive real-time view of the Teltonika Gateway Monitor system with KPIs, fleet status, and recent events. It's built using Livewire for real-time updates and follows accessibility and responsive design principles.

## Components

### 1. Livewire Dashboard Component (`App\Livewire\Dashboard`)

**Location**: `app/Livewire/Dashboard.php`

**Key Features**:
- Real-time KPI calculations
- Fleet status monitoring with sparkline charts
- Recent events timeline
- Auto-refresh every 30 seconds
- Event-driven updates via Livewire events

**Properties**:
- `$kpis`: Array containing Online Gateways, Poll Success Rate, and Average Latency metrics
- `$gateways`: Array of gateway data with status, success rates, and sparkline data
- `$recentEvents`: Array of recent system events (offline gateways, configuration changes)

**Methods**:
- `mount()`: Initialize component data
- `refreshDashboard()`: Refresh all dashboard data (triggered by events or polling)
- `loadDashboardData()`: Load all dashboard sections
- `loadKpis()`: Calculate KPI metrics
- `loadGateways()`: Load gateway fleet status
- `loadRecentEvents()`: Load recent system events

### 2. Dashboard View (`resources/views/livewire/dashboard.blade.php`)

**Key Features**:
- Responsive grid layout (1 column on mobile, 3 columns on desktop)
- Accessibility compliant with ARIA labels and semantic HTML
- Status indicators with color coding
- Mini sparkline charts for gateway activity
- Empty states with helpful guidance

**Sections**:
1. **KPI Tiles**: Online Gateways, Poll Success Rate, Average Latency
2. **Fleet Status Strip**: Gateway cards with status, metrics, and sparklines
3. **Recent Events Timeline**: Chronological list of system events

### 3. Filament Dashboard Page (`App\Filament\Pages\Dashboard`)

**Location**: `app/Filament/Pages/Dashboard.php`

Integrates the Livewire component into the Filament admin panel with proper page structure and navigation.

## KPI Calculations

### Online Gateways
- **Calculation**: Count of gateways with enabled data points that are currently online
- **Online Status**: Gateway is online if `last_seen_at` is within `(poll_interval * 2) + 30` seconds
- **Status Levels**: 
  - Good: All gateways online
  - Warning: Some gateways online
  - Error: No gateways online

### Poll Success Rate
- **Calculation**: Percentage of readings with 'good' quality from last 24 hours
- **Status Levels**:
  - Good: ≥95% success rate
  - Warning: 80-94% success rate
  - Error: <80% success rate

### Average Latency
- **Calculation**: Estimated based on poll intervals and failure rates
- **Formula**: `base_latency * failure_multiplier` (capped at 5000ms)
- **Status Levels**:
  - Good: ≤1000ms
  - Warning: 1001-3000ms
  - Error: >3000ms

## Fleet Status Features

### Gateway Cards
Each gateway card displays:
- Gateway name and IP:Port
- Online/Offline status with colored indicator
- Success rate percentage with color coding
- Number of configured data points
- Last seen timestamp (human-readable)
- Activity sparkline chart (last hour)

### Sparkline Charts
- Shows activity over the last hour
- Data points represent successful readings per time interval
- Responsive bar chart with hover tooltips
- Accessible with ARIA labels

## Recent Events

### Event Types
1. **Gateway Offline**: When a gateway hasn't been seen for >5 minutes
2. **Configuration Changed**: When gateway settings are updated

### Event Display
- Chronological timeline with visual indicators
- Color-coded severity levels (error, warning, info)
- Relative timestamps with full datetime on hover
- Gateway name and event description

## Responsive Design

### Breakpoints
- **Mobile (default)**: Single column layout, stacked tiles
- **Tablet (md)**: 3-column KPI grid, 2-column fleet grid
- **Desktop (lg)**: 3-column fleet grid
- **Large Desktop (xl)**: 4-column fleet grid

### Mobile Optimizations
- Touch-friendly interface elements
- Readable text sizes and spacing
- Horizontal scrolling for data tables
- Collapsible navigation

## Accessibility Features

### WCAG AA Compliance
- Semantic HTML structure with proper headings
- ARIA labels and descriptions for complex elements
- Color contrast ratios meet accessibility standards
- Keyboard navigation support
- Screen reader compatibility

### Implementation Details
- `aria-labelledby` for KPI values
- `role="img"` for sparkline charts
- `role="list"` for event timelines
- `aria-hidden="true"` for decorative icons
- Proper heading hierarchy (h1-h6)

## Real-time Updates

### Auto-refresh
- Component polls every 30 seconds via `wire:poll.30s="refreshDashboard"`
- Minimal data transfer by only updating changed values
- Visual loading states during refresh

### Event-driven Updates
- Listens for `gateway-updated` and `reading-created` events
- Immediate updates when system state changes
- Maintains user context during updates

## Performance Considerations

### Database Optimization
- Efficient queries with proper indexing
- Eager loading of relationships
- Scoped queries to reduce data transfer
- Caching of calculated metrics

### Frontend Optimization
- Minimal DOM updates via Livewire
- CSS-only animations and transitions
- Optimized image and icon usage
- Progressive enhancement approach

## Testing

### Test Coverage
- Component structure and method existence
- View template content and accessibility features
- KPI calculation accuracy
- Event handling and data refresh
- Responsive design classes
- Empty state handling

### Test Files
- `tests/Feature/DashboardComponentTest.php`: Full integration tests
- `tests/Unit/DashboardLogicTest.php`: Business logic tests
- `tests/Unit/DashboardComponentStructureTest.php`: Structure validation

## Usage

### Navigation
Access the dashboard via:
- Direct URL: `/admin`
- Navigation menu: "Dashboard" (first item)
- Filament panel default page

### User Interactions
- View real-time KPIs and fleet status
- Click gateway cards for detailed information
- Monitor recent events and system health
- Navigate to gateway management from empty states

### Customization
The dashboard can be customized by:
- Modifying KPI calculations in `loadKpis()`
- Adjusting refresh intervals in the view template
- Adding new event types in `loadRecentEvents()`
- Customizing responsive breakpoints in CSS

## Requirements Fulfilled

This implementation satisfies the following requirements:

- **1.1**: Dashboard displays KPI tiles with Online Gateways, Poll Success %, and Average Latency
- **1.2**: Fleet status strip shows gateway cards with sparkline charts and real-time updates
- **1.3**: Recent events timeline displays gateway offline and configuration events
- **1.4**: Responsive mobile layout with stacked tiles and proper accessibility features