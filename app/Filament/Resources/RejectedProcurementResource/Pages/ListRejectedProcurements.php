<?php

namespace App\Filament\Resources\RejectedProcurementResource\Pages;

use App\Filament\Resources\RejectedProcurementResource;
use Filament\Resources\Pages\ListRecords;

class ListRejectedProcurements extends ListRecords
{
    protected static string $resource = RejectedProcurementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}