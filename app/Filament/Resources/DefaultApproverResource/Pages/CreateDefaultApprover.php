<?php

namespace App\Filament\Resources\DefaultApproverResource\Pages;

use App\Filament\Resources\DefaultApproverResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use App\Mail\DefaultApproverAssignedMail;
use App\Helpers\ActivityLogger;
use App\Models\DefaultApprover;

class CreateDefaultApprover extends CreateRecord
{
    protected static string $resource = DefaultApproverResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Define max approvers per module
        $moduleApproverLimits = [
            'purchase_request' => 2,
            'request_for_quotation' => 2, // 1 for AFD, 1 for TOD
            'abstract_of_quotation' => 5,
            'minutes_of_opening' => 1,
            'bac_resolution_recommending_award' => 1,
            'purchase_order' => 2,
        ];

        $moduleNames = [
            'purchase_request' => 'Purchase Request',
            'request_for_quotation' => 'Request for Quotation',
            'abstract_of_quotation' => 'Abstract of Quotation',
            'minutes_of_opening' => 'Minutes of Opening',
            'bac_resolution_recommending_award' => 'BAC Resolution',
            'purchase_order' => 'Purchase Order',
        ];

        // Check if the module already has the maximum number of approvers
        if (isset($data['module'])) {
            // Special handling for Request for Quotation (check by office section)
            if ($data['module'] === 'request_for_quotation' && isset($data['office_section'])) {
                $existingForSection = DefaultApprover::where('module', 'request_for_quotation')
                    ->where('office_section', $data['office_section'])
                    ->count();
                
                if ($existingForSection >= 1) {
                    $sectionName = str_replace('DICT CAR - ', '', $data['office_section']);
                    
                    Notification::make()
                        ->title('Cannot create approver')
                        ->body("The Request for Quotation module already has 1 default approver assigned for {$sectionName}.")
                        ->danger()
                        ->send();
                        
                    $this->halt();
                }
            } else {
                // For other modules, check by module only
                $limit = $moduleApproverLimits[$data['module']] ?? 2;
                $existingCount = DefaultApprover::where('module', $data['module'])->count();
                
                if ($existingCount >= $limit) {
                    $moduleName = $moduleNames[$data['module']] ?? ucwords(str_replace('_', ' ', $data['module']));
                    
                    Notification::make()
                        ->title('Cannot create approver')
                        ->body("The {$moduleName} module already has the maximum number of {$limit} default approver(s) assigned.")
                        ->danger()
                        ->send();
                        
                    $this->halt();
                }
            }
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $approver = $this->record;
        
        // Get employee name (the person who was assigned)
        $employeeName = optional($approver->employee->user)->name ?? 'Unknown Employee';
        $module = ucfirst(str_replace('_', ' ', $approver->module ?? 'unspecified module'));
        $section = $approver->office_section ?? 'unspecified section';

        // ✅ Simplified and cleaner log message
        ActivityLogger::log(
            'Assigned Default Approver',
            "{$employeeName} was assigned as default approver for {$module} in {$section}."
        );

        // ✅ Send Gmail notification only to the assigned person
        if ($approver->employee && $approver->employee->user && $approver->employee->user->email) {
            Mail::to($approver->employee->user->email)
                ->send(new DefaultApproverAssignedMail($approver));
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