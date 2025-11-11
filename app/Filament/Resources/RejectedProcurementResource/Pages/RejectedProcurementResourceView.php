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

        $modules = [
            'ppmp' => 'Project Procurement Management Plan',
            'purchase_request' => 'Purchase Request',
            'request_for_quotation' => 'Request for Quotation',
            'abstract_of_quotation' => 'Abstract of Quotation',
            'bac_resolution_recommending_award' => 'BAC Resolution Recommending Award',
            'purchase_order' => 'Purchase Order',
        ];

        $sections = [];

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
                TextEntry::make('createdBy.name')
                    ->label('Created By')
                    ->state($record->createdBy->name ?? 'N/A'),
                TextEntry::make('rejected_at')
                    ->label('Rejected At')
                    ->date('Y-m-d H:i')
                    ->state($record->approvals()->where('status', 'Rejected')->orderBy('date_approved', 'desc')->first()?->date_approved),
                TextEntry::make('rejected_by')
                    ->label('Rejected By')
                    ->state($record->approvals()->where('status', 'Rejected')->orderBy('date_approved', 'desc')->first()?->employee->full_name ?? 'N/A'),
            ])
            ->columns(3)
            ->collapsible();

        foreach ($modules as $moduleKey => $label) {
            $child = $record->children()->where('module', $moduleKey)->first();
            $isRejected = $child && $child->approvals()->where('status', 'Rejected')->exists();

            $status = 'Pending';
            if ($child) {
                $approvals = $child->approvals()->where('module', $moduleKey)->get();
                if ($approvals->isEmpty()) {
                    $status = 'Not Started';
                } elseif ($approvals->contains('status', 'Rejected')) {
                    $status = 'Rejected';
                } elseif ($approvals->every(fn ($approval) => $approval->status === 'Approved')) {
                    $status = 'Approved';
                }
            }

            $sections[] = Section::make($label)
                ->schema([
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
                            'Not Started' => 'gray',
                            default => 'gray',
                        })
                        ->state($status),
                    TextEntry::make('remarks')
                        ->label('Rejection Remarks')
                        ->state($isRejected ? ($child->approvals()->where('status', 'Rejected')->first()?->remarks ?? 'N/A') : 'N/A'),
                ])
                ->columns(3)
                ->extraAttributes($isRejected ? ['class' => 'bg-red-200 dark:bg-red-800'] : [])
                ->collapsible();
        }

        return $infolist->schema($sections);
    }
}