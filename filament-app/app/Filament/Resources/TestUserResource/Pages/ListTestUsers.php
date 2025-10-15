<?php

namespace App\Filament\Resources\TestUserResource\Pages;

use App\Filament\Resources\TestUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTestUsers extends ListRecords
{
    protected static string $resource = TestUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}