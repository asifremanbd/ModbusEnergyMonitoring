<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    public function getTitle(): string
    {
        return 'Gateway Monitor Dashboard';
    }
    
    public function getHeading(): string
    {
        return 'Teltonika Gateway Monitor';
    }
    
    public function getSubheading(): ?string
    {
        return 'Monitor and manage your Modbus-enabled Teltonika energy meters';
    }
}