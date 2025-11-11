<?php

namespace App\Filament\Resources\ProcurementEmployeeResource\Pages;

use App\Filament\Resources\ProcurementEmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageProcurementEmployees extends ManageRecords
{
    protected static string $resource = ProcurementEmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
