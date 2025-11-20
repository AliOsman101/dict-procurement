<?php

namespace App\Filament\Resources\DefaultApproverResource\Pages;

use App\Filament\Resources\DefaultApproverResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use App\Mail\DefaultApproverAssignedMail;
use App\Helpers\ActivityLogger; 

class CreateDefaultApprover extends CreateRecord
{
    protected static string $resource = DefaultApproverResource::class;

    protected function afterCreate(): void
    {
        $approver = $this->record;

        
        if ($approver->employee && $approver->employee->user && $approver->employee->user->email) {
            Mail::to($approver->employee->user->email)
                ->send(new DefaultApproverAssignedMail($approver));
        }


        // Get employee name (the person who was assigned)
    $employeeName = optional($approver->employee->user)->name ?? 'Unknown Employee';
    $module = ucfirst(str_replace('_', ' ', $approver->module ?? 'unspecified module'));
    $section = $approver->office_section ?? 'unspecified section';

    // ✅ Simplified and cleaner log message
    \App\Helpers\ActivityLogger::log(
        'Assigned Default Approver',
        "{$employeeName} was assigned as default approver for {$module} in {$section}."
    );

    // ✅ Send Gmail notification only to the assigned person
    if ($approver->employee && $approver->employee->user && $approver->employee->user->email) {
        \Illuminate\Support\Facades\Mail::to($approver->employee->user->email)
            ->send(new \App\Mail\DefaultApproverAssignedMail($approver));
    }
}

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Default Approver created and notified via Gmail')
            ->success();
    }
}
