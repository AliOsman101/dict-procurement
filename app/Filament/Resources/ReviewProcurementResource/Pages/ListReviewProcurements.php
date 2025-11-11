<?php

namespace App\Filament\Resources\ReviewProcurementResource\Pages;

use App\Filament\Resources\ReviewProcurementResource;
use Filament\Resources\Pages\ListRecords;

class ListReviewProcurements extends ListRecords
{
    protected static string $resource = ReviewProcurementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}