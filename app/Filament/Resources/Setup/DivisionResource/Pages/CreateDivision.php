<?php

namespace App\Filament\Resources\Setup\DivisionResource\Pages;

use App\Filament\Resources\Setup\DivisionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDivision extends CreateRecord
{
    protected static string $resource = DivisionResource::class;

    protected function getRedirectUrl(): string
    {
        // Redirect to the list page after creation
        return static::getResource()::getUrl('index');
    }
}
