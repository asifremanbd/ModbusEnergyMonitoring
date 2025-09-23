<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PastReadings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    
    protected static string $view = 'filament.pages.past-readings';
    
    protected static ?string $navigationLabel = 'Past Readings';
    
    protected static ?string $title = 'Past Readings';
    
    protected static ?int $navigationSort = 3;
    
    protected static ?string $navigationGroup = 'Data';
    
    public function getTitle(): string
    {
        return 'Past Readings';
    }
    
    public function getHeading(): string
    {
        return 'Past Readings';
    }
}