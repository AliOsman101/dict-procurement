<?php

namespace App\Filament\Resources\FundClusterResource\Pages;

use App\Filament\Resources\FundClusterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Helpers\ActivityLogger;

class EditFundCluster extends EditRecord
{
    protected static string $resource = FundClusterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function ($record) {
                    $fundClusterName = $record->name ?? 'Unnamed Fund Cluster';
                    ActivityLogger::log('Deleted Fund Cluster', "Fund Cluster '{$fundClusterName}' was deleted.");
                }),
        ];
    }

    protected function afterSave(): void
    {
        $fundClusterName = $this->record->name ?? 'Unnamed Fund Cluster';
        ActivityLogger::log('Updated Fund Cluster', "Fund Cluster '{$fundClusterName}' was updated.");
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
