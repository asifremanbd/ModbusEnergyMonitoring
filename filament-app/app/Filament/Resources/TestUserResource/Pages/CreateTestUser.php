<?php

namespace App\Filament\Resources\TestUserResource\Pages;

use App\Filament\Resources\TestUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTestUser extends CreateRecord
{
    protected static string $resource = TestUserResource::class;
}