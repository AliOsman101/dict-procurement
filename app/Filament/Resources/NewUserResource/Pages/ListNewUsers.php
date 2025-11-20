<?php

namespace App\Filament\Resources\NewUserResource\Pages;

use App\Filament\Resources\NewUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewUsers extends ListRecords
{
    protected static string $resource = NewUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //       Actions\CreateAction::make(),
        ];
    }
}
