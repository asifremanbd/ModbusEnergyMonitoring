<?php

namespace App\Filament\Resources\TestUserResource\Pages;

use App\Filament\Resources\TestUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTestUser extends EditRecord
{
    protected static string $resource = TestUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}