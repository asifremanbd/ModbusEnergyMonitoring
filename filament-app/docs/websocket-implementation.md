# WebSocket Real-Time Communication Implementation

## Overview

This document describes the implementation of real-time WebSocket communication for the Teltonika Gateway Monitor application. The system provides live updates for gateway status changes and new data readings with automatic fallback to polling when WebSocket connections fail.

## Architecture

### Components

1. **Backend Events**
   - `NewReadingReceived` - Fired when new sensor readings are collected
   - `GatewayStatusChanged` - Fired when gateway online/offline status changes

2. **Frontend Integration**
   - Laravel Echo configuration for WebSocket connections
   - Livewire component listeners for real-time updates
   - Automatic fallback polling mechanism

3. **Broadcasting Channels**
   - `readings` - Global channel for all new readings
   - `gateways` - Global channel for gateway status changes
   - `gateway.{id}` - Specific gateway channels for targeted updates

## Configuration

### Environment Variables

Add the following to your `.env` file:

```env
BROADCAST_DRIVER=pusher

PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

### Testing Configuration

Use the built-in command to test your WebSocket setup:

```bash
php artisan websocket:test
```

This will verify:
- Broadcast driver configuration
- Pusher credentials
- Connection health status
- Fallback polling settings

## Implementation Details

### Event Broadcasting

#### NewReadingReceived Event

Automatically fired when readings are created in `ModbusPollService`:

```php
// Create and save reading
$reading = Reading::create([
    'data_point_id' => $dataPoint->id,
    'raw_value' => json_encode($registers),
    'scaled_value' => $scaledValue,
    'quality' => 'good',
    'read_at' => Carbon::now()
]);

// Broadcast new reading event
NewReadingReceived::dispatch($reading);
```

**Broadcast Data:**
- Reading details (ID, value, quality, timestamp)
- Data point information (gateway, group, label)
- ISO timestamp for client-side processing

#### GatewayStatusChanged Event

Fired when gateway status changes in `PollGatewayJob`:

```php
$previousStatus = $gateway->is_active ? 'active' : 'inactive';

$gateway->update(['is_active' => false]);

// Broadcast gateway status change
GatewayStatusChanged::dispatch($gateway, $previousStatus, 'inactive');
```

**Broadcast Data:**
- Gateway details (ID, name, IP, status counters)
- Status change information (previous, current, timestamp)

### Frontend Integration

#### Laravel Echo Setup

Configured in `resources/js/bootstrap.js`:

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
    // ... additional configuration
});
```

#### Livewire Component Listeners

Components automatically listen for WebSocket events:

```php
#[On('echo:readings,reading.new')]
#[On('echo:gateways,gateway.status-changed')]
public function refreshDashboard()
{
    $this->loadDashboardData();
}
```

#### JavaScript Event Handling

Each component includes WebSocket connection monitoring:

```javascript
// Listen for new readings
window.Echo.channel('readings')
    .listen('.reading.new', (e) => {
        console.log('New reading received:', e);
        @this.call('refreshDashboard');
    });

// Connection status monitoring
window.Echo.connector.pusher.connection.bind('connected', () => {
    isConnected = true;
    clearFallbackPolling();
});

window.Echo.connector.pusher.connection.bind('disconnected', () => {
    isConnected = false;
    startFallbackPolling();
});
```

### Fallback Polling Mechanism

When WebSocket connections fail, the system automatically falls back to polling:

#### Automatic Fallback

- **Connection Monitoring**: JavaScript monitors WebSocket connection status
- **Intelligent Intervals**: Polling frequency adjusts based on connection history
- **Seamless Transition**: Users experience no interruption in data updates

#### Polling Intervals

- **First Fallback**: 5 seconds (immediate response to disconnection)
- **Early Attempts**: 10 seconds (for temporary connection issues)
- **Persistent Issues**: 30 seconds (for extended outages)

#### Implementation

```javascript
function startFallbackPolling() {
    if (fallbackInterval) return;
    
    fallbackInterval = setInterval(() => {
        if (!isConnected) {
            @this.call('refreshDashboard');
        }
    }, getFallbackInterval());
}
```

## Channel Authorization

### Public Channels

All monitoring channels are public for authenticated users:

```php
// routes/channels.php
Broadcast::channel('readings', function () {
    return true; // Public for all authenticated users
});

Broadcast::channel('gateways', function () {
    return true; // Public for all authenticated users
});

Broadcast::channel('gateway.{gatewayId}', function ($user, $gatewayId) {
    return true; // Public for specific gateway updates
});
```

### Security Considerations

- All channels require user authentication
- No sensitive data is broadcast (only IDs and status information)
- Gateway-specific channels could be restricted based on user permissions

## Performance Optimization

### Event Queuing

Events are dispatched asynchronously to avoid blocking the polling process:

```php
// Events implement ShouldBroadcast for automatic queuing
class NewReadingReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    // ...
}
```

### Data Minimization

Only essential data is broadcast to reduce bandwidth:

- Reading events include only current value and metadata
- Gateway events include only status-relevant information
- Historical data is fetched on-demand through component refreshes

### Connection Pooling

WebSocket connections are shared across components:

- Single Echo instance per page
- Multiple channel subscriptions on same connection
- Automatic reconnection handling

## Monitoring and Debugging

### WebSocket Service

The `WebSocketService` provides connection health monitoring:

```php
$webSocketService = app(WebSocketService::class);
$health = $webSocketService->getHealthStatus();
```

**Health Metrics:**
- Connection status
- Fallback polling status
- Recent connection attempts
- Recommended polling intervals

### Logging

WebSocket events are logged for debugging:

```php
Log::info('WebSocket connected');
Log::warning('WebSocket disconnected');
Log::error('WebSocket error:', $error);
```

### Browser Console

Client-side logging helps with troubleshooting:

```javascript
console.log('New reading received:', e);
console.log('WebSocket connected');
console.error('WebSocket error:', error);
```

## Testing

### Unit Tests

- Event broadcasting verification
- Channel configuration testing
- Data structure validation

### Integration Tests

- Livewire component WebSocket integration
- Fallback polling mechanism
- End-to-end real-time updates

### Manual Testing

1. **WebSocket Configuration**: `php artisan websocket:test`
2. **Real-time Updates**: Monitor browser console during gateway polling
3. **Fallback Testing**: Disable WebSocket and verify polling activation

## Deployment Considerations

### Production Setup

1. **Pusher Account**: Set up production Pusher application
2. **Environment Variables**: Configure production credentials
3. **SSL/TLS**: Ensure secure WebSocket connections (WSS)
4. **Monitoring**: Set up alerts for WebSocket connection failures

### Scaling

- **Multiple Servers**: WebSocket events work across load-balanced servers
- **Queue Workers**: Ensure sufficient queue workers for event processing
- **Connection Limits**: Monitor Pusher connection limits and upgrade as needed

### Backup Strategy

- **Fallback Polling**: Always available as backup
- **Graceful Degradation**: Application remains functional without WebSocket
- **User Notification**: Consider notifying users of connection status

## Troubleshooting

### Common Issues

1. **Missing Pusher Credentials**
   - Run `php artisan websocket:test`
   - Verify `.env` configuration
   - Check Pusher dashboard

2. **WebSocket Connection Failures**
   - Check browser console for errors
   - Verify SSL/TLS configuration
   - Test with different networks/firewalls

3. **Events Not Broadcasting**
   - Verify queue workers are running
   - Check Laravel logs for errors
   - Confirm event listeners are registered

### Debug Commands

```bash
# Test WebSocket configuration
php artisan websocket:test

# Check queue status
php artisan queue:work --verbose

# Monitor Laravel logs
tail -f storage/logs/laravel.log
```

## Future Enhancements

### Potential Improvements

1. **User-Specific Channels**: Personalized data streams based on user permissions
2. **Real-time Alerts**: Instant notifications for critical gateway events
3. **Historical Playback**: Real-time replay of historical data
4. **Mobile Push**: Integration with mobile push notifications
5. **WebRTC**: Direct peer-to-peer connections for ultra-low latency

### Performance Optimizations

1. **Event Batching**: Group multiple readings into single broadcasts
2. **Selective Updates**: Only broadcast changed values
3. **Compression**: Compress broadcast payloads
4. **CDN Integration**: Use CDN for WebSocket connections