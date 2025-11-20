<?php

namespace App\Filament\Resources\RejectedProcurementResource\Pages;

use App\Filament\Resources\RejectedProcurementResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class RejectedProcurementResourceView extends ViewRecord
{
    protected static string $resource = RejectedProcurementResource::class;

    public function getTitle(): string
    {
        return "Procurement No. " . ($this->record->procurement_id ?? 'N/A');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $record = $this->record;
        $employeeId = Auth::user()->employee->id ?? null;

        if (!$employeeId) {
            abort(403, 'Unauthorized: No employee associated with this user.');
        }

        // Define module order and their display names
        $modules = [
            'ppmp' => 'Project Procurement Management Plan',
            'purchase_request' => 'Purchase Request',
            'request_for_quotation' => 'Request for Quotation',
            'abstract_of_quotation' => 'Abstract of Quotation',
            'bac_resolution_recommending_award' => 'BAC Resolution Recommending Award',
            'purchase_order' => 'Purchase Order',
        ];

        // Route mapping for view links
        $moduleRoutes = [
            'ppmp' => 'filament.admin.resources.procurements.view-ppmp',
            'purchase_request' => 'filament.admin.resources.procurements.view-pr',
            'request_for_quotation' => 'filament.admin.resources.procurements.view-rfq',
            'abstract_of_quotation' => 'filament.admin.resources.procurements.view-aoq',
            'bac_resolution_recommending_award' => 'filament.admin.resources.procurements.view-bac',
            'purchase_order' => 'filament.admin.resources.procurements.view-po',
        ];

        // Find which module was rejected and its position
        $rejectedModuleKey = null;
        $rejectedModulePosition = null;
        
        foreach (array_keys($modules) as $index => $moduleKey) {
            $child = $record->children()->where('module', $moduleKey)->first();
            if ($child && $child->approvals()->where('status', 'Rejected')->exists()) {
                $rejectedModuleKey = $moduleKey;
                $rejectedModulePosition = $index;
                break;
            }
        }

        $sections = [];

        // Get rejection details from the rejected module
        $rejectedChild = $rejectedModuleKey ? $record->children()->where('module', $rejectedModuleKey)->first() : null;
        $rejectionApproval = $rejectedChild ? $rejectedChild->approvals()->where('status', 'Rejected')->orderBy('action_at', 'desc')->first() : null;

        // Procurement Details Section
        $sections[] = Section::make('Procurement Details')
            ->schema([
                TextEntry::make('procurement_id')
                    ->label('Procurement ID')
                    ->state($record->procurement_id ?? 'N/A'),
                TextEntry::make('title')
                    ->label('Title')
                    ->state($record->title ?? 'N/A'),
                TextEntry::make('status')
                    ->label('Status')
                    ->badge()
                    ->color('danger')
                    ->state('Rejected'),
                TextEntry::make('created_at')
                    ->label('Date Created')
                    ->date('Y-m-d')
                    ->state($record->created_at),
                TextEntry::make('created_by')
                    ->label('Created By')
                    ->state($record->creator?->name ?? 'N/A'),
                TextEntry::make('rejected_at')
                    ->label('Rejected At')
                    ->dateTime('M d, Y - h:i A')
                    ->state($rejectionApproval?->action_at),
                TextEntry::make('rejected_by')
                    ->label('Rejected By')
                    ->state($rejectionApproval?->employee?->full_name ?? 'N/A'),
            ])
            ->columns(3)
            ->collapsible();

        // Module Sections - Only show modules up to and including the rejected one
        foreach ($modules as $moduleKey => $label) {
            $currentPosition = array_search($moduleKey, array_keys($modules));
            
            // Skip modules that come after the rejected module
            if ($rejectedModulePosition !== null && $currentPosition > $rejectedModulePosition) {
                continue;
            }

            $child = $record->children()->where('module', $moduleKey)->first();
            $isRejected = $child && $child->approvals()->where('status', 'Rejected')->exists();

            // Determine module status
            $status = 'Pending';

            if ($child) {

                // Special rule for PPMP: check if uploaded
                if ($moduleKey === 'ppmp') {
                    if ($child->documents()->where('module', 'ppmp')->exists()) {
                        $status = 'Uploaded';
                    } else {
                        $status = 'Not Started';
                    }
                } else {
                    // Standard approval logic for all other modules
                    $approvals = $child->approvals()->where('module', $moduleKey)->get();

                    if ($approvals->isEmpty()) {
                        $status = 'Not Started';
                    } elseif ($approvals->contains('status', 'Rejected')) {
                        $status = 'Rejected';
                    } elseif ($approvals->every(fn ($approval) => $approval->status === 'Approved')) {
                        $status = 'Approved';
                    }
                }
            }

            // Build schema for this module
            $schema = [
                TextEntry::make('doc_no')
                    ->label($label . ' No.')
                    ->state($child ? $child->procurement_id : 'N/A'),
                TextEntry::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(match ($status) {
                        'Approved' => 'success',
                        'Rejected' => 'danger',
                        'Pending' => 'warning',
                        'Uploaded' => 'info',
                        'Not Started' => 'gray',
                        default => 'gray',
                    })
                    ->state($status),
                TextEntry::make('document')
                    ->label('Document')
                    ->html()
                    ->state(function () use ($record, $moduleKey, $moduleRoutes) {
                        if (isset($moduleRoutes[$moduleKey])) {
                            return '<a href="' . route($moduleRoutes[$moduleKey], $record) . '" style="text-decoration: underline;">View</a>';
                        }
                        return 'N/A';
                    }),
            ];

            // Add rejection remarks column ONLY if this module is rejected
            if ($isRejected) {
                $rejectionRemarks = $child->approvals()
                    ->where('status', 'Rejected')
                    ->orderBy('action_at', 'desc')
                    ->first()?->remarks ?? 'No remarks provided';
                
                $schema[] = TextEntry::make('rejection_remarks')
                    ->label('Rejection Remarks')
                    ->state($rejectionRemarks)
                    ->columnSpanFull();
            }

            $sections[] = Section::make($label)
                ->schema($schema)
                ->columns($isRejected ? 3 : 3)
                ->extraAttributes($isRejected ? ['class' => 'bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500'] : [])
                ->collapsible();
        }

        return $infolist->schema($sections);
    }
}