<?php

namespace App\Filament\Resources\DefaultApproverResource\Pages;

use App\Filament\Resources\DefaultApproverResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditDefaultApprover extends EditRecord
{
    protected static string $resource = DefaultApproverResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Default Approver updated')
            ->success();
    }
}