<?php

namespace App\Filament\Resources\Setup\DivisionResource\Pages;

use App\Filament\Resources\Setup\DivisionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDivision extends EditRecord
{
    protected static string $resource = DivisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        // Redirect to the list page after creation
        return static::getResource()::getUrl('index');
    }
}
