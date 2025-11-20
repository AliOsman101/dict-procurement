<?php

namespace App\Filament\Resources\CompletedProcurementResource\Pages;

use App\Filament\Resources\CompletedProcurementResource;
use Filament\Resources\Pages\ListRecords;

class ListCompletedProcurements extends ListRecords
{
    protected static string $resource = CompletedProcurementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}