<?php

namespace App\Filament\Resources\ProcurementResource\Pages;

use App\Filament\Resources\ProcurementResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;

class ProcurementView extends ViewRecord
{
    protected static string $resource = ProcurementResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make()
                    ->schema([
                        TextEntry::make('overall_status')
                            ->label('Overall Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Pending'   => 'warning',
                                'Completed' => 'success',
                                default     => 'secondary',
                            })
                            ->getStateUsing(fn ($record) => $this->calculateOverallStatus($record)),
                        TextEntry::make('created_at')
                            ->label('Date Filed')
                            ->date('Y-m-d'),
                        TextEntry::make('title')
                            ->label('Title'),
                        TextEntry::make('procurement_type')
                            ->label('Procurement Type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state === 'small_value_procurement' ? 'Small Value Procurement' : $state)
                            ->color(fn ($state) => $state === 'small_value_procurement' ? 'info' : 'primary'),
                    ])
                    ->columns(4),

                Section::make('Project Procurement Management Plan')
                    ->schema([
                        TextEntry::make('ppmp_procurement_id')
                            ->label('PPMP No.')
                            ->getStateUsing(fn ($record) =>
                                $record->children->where('module', 'ppmp')->first()?->procurement_id ?? 'N/A'
                            ),
                        TextEntry::make('ppmp_status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'Approved'  => 'success',
                                'Uploaded'  => 'info',
                                'Pending'   => 'warning',
                                'Locked'    => 'danger',
                                'Rejected'  => 'gray', 
                                default     => 'gray',
                            })
                            ->getStateUsing(function ($record) {
                                $ppmp = $record->children->where('module', 'ppmp')->first();
                                if ($ppmp && $ppmp->documents()->where('module', 'ppmp')->exists()) {
                                    return 'Uploaded';
                                }
                                return $ppmp?->status ?? 'Pending';
                            }),
                        TextEntry::make('ppmp_document')
                            ->label('Document')
                            ->html()
                            ->getStateUsing(fn ($record) =>
                                '<a href="' . route('filament.admin.resources.procurements.view-ppmp', $record) . '" style="text-decoration: underline;">View PPMP</a>'
                            ),
                    ])
                    ->columns(3),

                Section::make('Purchase Request')
                    ->schema([
                        TextEntry::make('pr_procurement_id')
                            ->label('Purchase Request No.')
                            ->getStateUsing(fn ($record) =>
                                $record->children->where('module', 'purchase_request')->first()?->procurement_id ?? 'N/A'
                            ),
                        TextEntry::make('pr_status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'Approved' => 'success',
                                'Pending'  => 'warning',
                                'Locked'   => 'danger',
                                'Rejected' => 'danger',
                                default    => 'gray',
                            })
                            ->getStateUsing(fn ($record) =>
                                $this->getModuleStatus($record, 'purchase_request')
                            ),
                        TextEntry::make('pr_document')
                            ->label('Document')
                            ->html()
                            ->getStateUsing(fn ($record) =>
                                '<a href="' . route('filament.admin.resources.procurements.view-pr', $record) . '" style="text-decoration: underline;">View</a>'
                            ),
                    ])
                    ->columns(3),

                Section::make('Request for Quotation')
                    ->schema([
                        TextEntry::make('rfq_procurement_id')
                            ->label('Request for Quotation No.')
                            ->getStateUsing(fn ($record) =>
                                $record->children->where('module', 'request_for_quotation')->first()?->procurement_id ?? 'N/A'
                            ),
                        TextEntry::make('rfq_status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'Approved' => 'success',
                                'Pending'  => 'warning',
                                'Locked'   => 'danger',
                                'Rejected' => 'danger',
                                default    => 'gray',
                            })
                            ->getStateUsing(fn ($record) =>
                                $this->getModuleStatus($record, 'request_for_quotation')
                            ),
                        TextEntry::make('rfq_document')
                            ->label('Document')
                            ->html()
                            ->getStateUsing(fn ($record) =>
                                '<a href="' . route('filament.admin.resources.procurements.view-rfq', $record) . '" style="text-decoration: underline;">View</a>'
                            ),
                    ])
                    ->columns(3),

                Section::make('Abstract of Quotation')
                    ->schema([
                        TextEntry::make('aoq_procurement_id')
                            ->label('Abstract of Quotation No.')
                            ->getStateUsing(fn ($record) =>
                                $record->children->where('module', 'abstract_of_quotation')->first()?->procurement_id ?? 'N/A'
                            ),
                        TextEntry::make('aoq_status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'Approved' => 'success',
                                'Pending'  => 'warning',
                                'Locked'   => 'danger',
                                'Rejected' => 'danger',
                                default    => 'gray',
                            })
                            ->getStateUsing(fn ($record) =>
                                $this->getModuleStatus($record, 'abstract_of_quotation')
                            ),
                        TextEntry::make('aoq_document')
                            ->label('Document')
                            ->html()
                            ->getStateUsing(fn ($record) =>
                                '<a href="' . route('filament.admin.resources.procurements.view-aoq', $record) . '" style="text-decoration: underline;">View</a>'
                            ),
                    ])
                    ->columns(3),

                Section::make('BAC Resolution Recommending Award')
                    ->schema([
                        TextEntry::make('bac_procurement_id')
                            ->label('BAC Resolution No.')
                            ->getStateUsing(fn ($record) =>
                                $record->children->where('module', 'bac_resolution_recommending_award')->first()?->procurement_id ?? 'N/A'
                            ),
                        TextEntry::make('bac_status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'Approved' => 'success',
                                'Pending'  => 'warning',
                                'Locked'   => 'danger',
                                'Rejected' => 'danger',
                                default    => 'gray',
                            })
                            ->getStateUsing(fn ($record) =>
                                $this->getModuleStatus($record, 'bac_resolution_recommending_award')
                            ),
                        TextEntry::make('bac_document')
                            ->label('Document')
                            ->html()
                            ->getStateUsing(fn ($record) =>
                                '<a href="' . route('filament.admin.resources.procurements.view-bac', $record) . '" style="text-decoration: underline;">View</a>'
                            ),
                    ])
                    ->columns(3),

                Section::make('Purchase Order')
                    ->schema([
                        TextEntry::make('po_procurement_id')
                            ->label('Purchase Order No.')
                            ->getStateUsing(fn ($record) =>
                                $record->children->where('module', 'purchase_order')->first()?->procurement_id ?? 'N/A'
                            ),
                        TextEntry::make('po_status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'Approved' => 'success',
                                'Pending'  => 'warning',
                                'Locked'   => 'danger',
                                'Rejected' => 'danger',
                                default    => 'gray',
                            })
                            ->getStateUsing(fn ($record) =>
                                $this->getModuleStatus($record, 'purchase_order')
                            ),
                        TextEntry::make('po_document')
                            ->label('Document')
                            ->html()
                            ->getStateUsing(fn ($record) =>
                                '<a href="' . route('filament.admin.resources.procurements.view-po', $record) . '" style="text-decoration: underline;">View</a>'
                            ),
                    ])
                    ->columns(3),
            ]);
    }

    /**
     * Calculate overall procurement status
     * Completed: ALL modules approved AND PPMP uploaded
     * Pending: One or more modules pending OR PPMP not uploaded
     */
    private function calculateOverallStatus($record): string
    {
        // Check if PPMP is uploaded
        $ppmpModule = $record->children->where('module', 'ppmp')->first();
        $ppmpUploaded = $ppmpModule && $ppmpModule->documents()->where('module', 'ppmp')->exists();
        
        if (!$ppmpUploaded) {
            return 'Pending';
        }

        // Check all required modules are approved
        $requiredModules = [
            'purchase_request',
            'request_for_quotation',
            'abstract_of_quotation',
            'bac_resolution_recommending_award',
            'purchase_order'
        ];

        foreach ($requiredModules as $module) {
            $moduleRecord = $record->children->where('module', $module)->first();
            
            if (!$moduleRecord) {
                return 'Pending';
            }

            // Check if module has approved status from all approvers
            $hasApprovedApproval = $moduleRecord->approvals()
                ->where('status', 'Approved')
                ->exists();
            
            if (!$hasApprovedApproval) {
                return 'Pending';
            }
        }

        return 'Completed';
    }

    /**
     * Get module status - returns Approved only if all approvers approved
     */
    private function getModuleStatus($record, string $module): string
    {
        $moduleRecord = $record->children->where('module', $module)->first();
        
        if (!$moduleRecord) {
            return 'Pending';
        }

        // Check if there are any approvals
        $hasApprovals = $moduleRecord->approvals()->exists();
        
        if (!$hasApprovals) {
            return 'Pending';
        }

        // Check if all approvals are approved
        $allApproved = $moduleRecord->approvals()
            ->where('status', 'Approved')
            ->exists();

        return $allApproved ? 'Approved' : $moduleRecord->status ?? 'Pending';
    }
}