<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class LiveData extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    
    protected static ?string $navigationLabel = 'Live Data';
    
    protected static ?int $navigationSort = 3;
    
    protected static string $view = 'filament.pages.live-data';
    
    protected static ?string $title = 'Live Data Readings';
    
    protected static ?string $slug = 'live-data';
    
    public function getTitle(): string
    {
        return 'Live Data Readings';
    }
    
    public function getHeading(): string
    {
        return 'Live Data Readings';
    }
}