<?php

namespace App\Filament\Resources\Setup\ProjectResource\Pages;

use App\Filament\Resources\Setup\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

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
