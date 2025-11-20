<?php

namespace App\Filament\Resources\FundClusterResource\Pages;

use App\Filament\Resources\FundClusterResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Helpers\ActivityLogger; 

class CreateFundCluster extends CreateRecord
{
    protected static string $resource = FundClusterResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        ActivityLogger::log(
            'Created Fund Cluster',
            'Fund Cluster "' . $this->record->name . '" was created.'
        );
    }
}