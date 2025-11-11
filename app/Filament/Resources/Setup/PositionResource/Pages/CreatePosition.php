<?php

namespace App\Filament\Resources\Setup\PositionResource\Pages;

use App\Filament\Resources\Setup\PositionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePosition extends CreateRecord
{
    protected static string $resource = PositionResource::class;

    protected function getRedirectUrl(): string
    {
        // Redirect to the list page after creation
        return static::getResource()::getUrl('index');
    }
}
