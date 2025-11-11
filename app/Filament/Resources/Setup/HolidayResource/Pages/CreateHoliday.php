<?php

namespace App\Filament\Resources\Setup\HolidayResource\Pages;

use App\Filament\Resources\Setup\HolidayResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateHoliday extends CreateRecord
{
    protected static string $resource = HolidayResource::class;


    protected function getRedirectUrl(): string
    {
        // Redirect to the list page after creation
        return static::getResource()::getUrl('index');
    }
}
