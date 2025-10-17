# Teltonika Gateway Monitor

A Laravel-based web application for monitoring Modbus energy data from Teltonika gateways. This system provides real-time data visualization, historical analysis, and comprehensive gateway management through a modern web interface.

## Features

- **Real-time Data Monitoring**: Live dashboard showing current energy readings from connected gateways
- **Historical Data Analysis**: View and analyze past readings with filtering and export capabilities  
- **Gateway Management**: Configure and manage multiple Teltonika gateways and their connected devices
- **Device Configuration**: Set up Modbus registers for various energy meters and sensors
- **Automated Polling**: Background job system for reliable data collection
- **User Management**: Role-based access control with Filament admin panel
- **Responsive Design**: Mobile-friendly interface for monitoring on any device

## Quick Start

### Prerequisites

- PHP 8.1+
- MySQL 5.7+ or MariaDB 10.3+
- Composer
- Node.js & npm (for asset compilation)

### Installation

1. **Clone and setup**
   ```bash
   git clone <repository-url>
   cd filament-app
   composer install
   npm install && npm run build
   ```

2. **Environment configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database setup**
   ```bash
   # Configure database in .env file
   php artisan migrate
   php artisan db:seed
   ```

4. **Start the application**
   ```bash
   php artisan serve
   php artisan queue:work  # In separate terminal for background jobs
   ```

5. **Access the admin panel**
   - URL: `http://localhost:8000/admin`
   - Default credentials created by seeder

## API Integration

### Data Ingestion Endpoint

External Python Modbus pollers can send data to:

```
POST /api/v1/readings/ingest
Authorization: Bearer {READINGS_INGEST_TOKEN}
Content-Type: application/json

{
  "gateway_ip": "192.168.1.100",
  "device_name": "Main Meter",
  "register_address": 1001,
  "value": 245.7,
  "timestamp": "2024-01-15T10:30:00Z"
}
```

Set `READINGS_INGEST_TOKEN` in your `.env` file for API authentication.

## Configuration

### Gateway Setup
1. Navigate to Admin Panel → Gateways
2. Add gateway with IP address and Modbus settings
3. Configure connected devices and their registers
4. Enable polling to start data collection

### Polling System
- Automatic polling runs via Laravel queue jobs
- Configure polling intervals per gateway
- Monitor polling health via admin dashboard
- Logs available in `storage/logs/`

## Architecture

- **Backend**: Laravel 10 with Filament admin panel
- **Frontend**: Livewire components with Alpine.js
- **Database**: MySQL with optimized indexes for time-series data
- **Queue System**: Database-backed job processing
- **Real-time Updates**: WebSocket integration for live data

## Documentation

Detailed documentation available in `/docs/`:
- Gateway configuration guide
- API reference
- Polling system setup
- WebSocket implementation
- Accessibility features

## License

This project is licensed under the MIT License.
