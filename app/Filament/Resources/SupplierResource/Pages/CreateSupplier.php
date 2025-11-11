<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Resources\Pages\CreateRecord;
use App\Helpers\ActivityLogger;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $supplierName = $this->record->name 
            ?? $this->record->business_name 
            ?? 'Unnamed Supplier';

        ActivityLogger::log(
            'Created Supplier',
            "Supplier '{$supplierName}' was created."
        );
    }
}
