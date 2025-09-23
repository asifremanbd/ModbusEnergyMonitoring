# Modbus Energy Monitoring System

A comprehensive Laravel Filament application for monitoring energy consumption through Teltonika gateways using Modbus TCP communication.

## Features

- **Gateway Management**: Configure and manage Teltonika gateways
- **Real-time Data Monitoring**: Live energy consumption tracking
- **Modbus TCP Communication**: Direct communication with energy meters
- **Interactive Dashboard**: Real-time charts and statistics
- **Data Point Configuration**: Flexible energy meter register mapping
- **WebSocket Integration**: Live data updates without page refresh
- **Comprehensive Testing**: Unit, integration, and performance tests

## Tech Stack

- **Backend**: Laravel 10 with PHP 8.1+
- **Admin Panel**: Filament 3.x
- **Database**: MySQL
- **Real-time**: WebSockets with Pusher
- **Frontend**: Livewire, Alpine.js, Tailwind CSS
- **Modbus**: aldas/modbus-tcp-client

## Quick Start

### Prerequisites

- PHP 8.1 or higher
- Composer
- Node.js & npm
- MySQL
- Git

### Installation

1. **Clone the repository**
```bash
git clone <repository-url>
cd ModbusEnergyMonitoring
```

2. **Install dependencies**
```bash
cd filament-app
composer install
npm install && npm run build
```

3. **Environment setup**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure database**
Update `.env` with your database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=filament_app
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

5. **Run migrations**
```bash
php artisan migrate
php artisan db:seed
```

6. **Start the application**
```bash
php artisan serve
```

Visit `http://localhost:8000/admin` to access the admin panel.

## Project Structure

```
filament-app/
├── app/
│   ├── Console/Commands/     # Modbus testing and database backup commands
│   ├── Filament/            # Admin panel resources and pages
│   ├── Jobs/                # Background job processing
│   ├── Livewire/            # Real-time components
│   ├── Models/              # Database models
│   └── Services/            # Business logic services
├── database/
│   ├── migrations/          # Database schema
│   ├── migration-scripts/   # Ubuntu production migration tools
│   └── seeders/            # Sample data
├── docs/                   # Comprehensive documentation
├── resources/
│   └── views/livewire/     # Livewire component views
└── tests/                  # Complete test suite including migration tests
```

## Key Components

### Services
- **ModbusPollService**: Handles Modbus TCP communication
- **GatewayPollingService**: Manages gateway polling schedules
- **TeltonikaTemplateService**: Gateway configuration templates
- **DataTypeConversionService**: Energy meter data processing

### Migration Tools
- **DatabaseBackup**: Automated backup with compression and validation
- **DatabaseRestore**: Secure restore with SSH tunneling support
- **MySQLReplication**: Master-slave replication setup and monitoring
- **MigrationOrchestrator**: Complete migration workflow management
- **SynchronizationMonitor**: Real-time sync status tracking

### Commands
- `php artisan gateway:start-polling {gateway}` - Start gateway monitoring
- `php artisan gateway:stop-polling {gateway}` - Stop gateway monitoring
- `php artisan gateway:status` - Check polling status
- `php artisan test:modbus` - Test Modbus connections
- `php artisan database:backup` - Create database backup with compression
- `php artisan database:restore` - Restore database from backup

### Models
- **Gateway**: Teltonika gateway configuration
- **DataPoint**: Energy meter register mappings
- **Reading**: Historical energy consumption data

## Testing

Run the comprehensive test suite:

```bash
# Unit tests
php artisan test --testsuite=Unit

# Feature tests
php artisan test --testsuite=Feature

# Migration component tests
php artisan test tests/Unit/MigrationComponents/
php artisan test tests/Integration/MigrationIntegrationTest.php

# Performance tests
php artisan test tests/Performance/

# Complete test suite
php artisan test

# Migration script testing
cd filament-app/database/migration-scripts
php run-tests.php
```

## Deployment

### Production Server Setup

1. **Server Requirements**
   - Ubuntu 20.04+ or similar
   - PHP 8.1+ with extensions
   - Nginx or Apache
   - MySQL 8.0+
   - Supervisor (for queue workers)

2. **Database Migration Tools**
   The project includes comprehensive database migration utilities for Ubuntu production servers:
   ```bash
   # MySQL setup on Ubuntu
   cd filament-app/database/migration-scripts
   ./install-mysql-ubuntu.sh
   ./configure-mysql-ubuntu.sh
   
   # Database backup and restore
   php backup-database.php
   php restore-database.php
   
   # Migration orchestration
   php migrate-database.php
   ```

3. **Quick Deployment**
   Use the provided deployment scripts:
   ```bash
   # Run server setup
   ./setup-server.sh
   
   # Deploy application
   ./deploy.sh
   ```

4. **Manual Deployment**
   See `deploy-guide.md` for detailed instructions.

## Configuration

### Gateway Setup
1. Access admin panel at `/admin`
2. Navigate to Gateways
3. Add new gateway with Teltonika device details
4. Configure data points for connected energy meters
5. Start polling to begin data collection

### Data Points
Configure energy meter registers in the admin panel:
- **Register Address**: Modbus register number
- **Data Type**: Float32, Int16, etc.
- **Scale Factor**: Value multiplication factor
- **Unit**: kWh, kW, V, A, etc.

## API Integration

The system provides WebSocket endpoints for real-time data:

```javascript
// Connect to live data feed
const socket = new WebSocket('ws://your-domain/ws');
socket.onmessage = function(event) {
    const data = JSON.parse(event.data);
    // Handle real-time energy data
};
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## License

This project is licensed under the MIT License.

## Support

For issues and questions:
- Check the `docs/` directory for detailed documentation
- Review test files for usage examples
- Create an issue for bugs or feature requests

## Database Migration

The project includes a complete database migration toolkit for Ubuntu production servers:

### Migration Components
- **Automated MySQL Installation**: Ubuntu-specific MySQL 8.0 setup
- **Database Backup/Restore**: Compressed backups with integrity validation
- **Replication Setup**: Master-slave replication with monitoring
- **Configuration Management**: Automated Laravel config updates
- **Migration Orchestration**: End-to-end migration workflow
- **Sync Monitoring**: Real-time synchronization status tracking

### Migration Scripts Location
All migration tools are located in `filament-app/database/migration-scripts/` with comprehensive documentation and testing utilities.

## Changelog

### v1.1.0
- Added comprehensive database migration toolkit
- Ubuntu production server automation
- MySQL replication setup and monitoring
- Database backup/restore with compression
- Migration orchestration system
- Extensive migration testing suite

### v1.0.0
- Initial release with core monitoring functionality
- Teltonika gateway integration
- Real-time dashboard
- Comprehensive test suite