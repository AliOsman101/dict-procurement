<?php

namespace App\Filament\Resources\ReviewProcurementResource\Pages;

use App\Filament\Resources\ReviewProcurementResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Actions\Action; // Correct import
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use App\Models\Procurement;
use Carbon\Carbon;
use App\Helpers\ActivityLogger;

class ApproverViewBac extends ViewRecord
{
    protected static string $resource = ReviewProcurementResource::class;

    public function mount($record): void
    {
        $child = Procurement::where('parent_id', $record)
                            ->where('module', 'bac_resolution_recommending_award')
                            ->firstOrFail();
        $this->record = $child;
        $this->record->refresh();
    }

    public function getTitle(): string
    {
        return "BAC Resolution No. " . ($this->record->procurement_id ?? 'N/A');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('BAC Resolution Details')
                    ->schema([
                        TextEntry::make('procurement_id')
                            ->label('BAC Resolution No.'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Pending' => 'warning',
                                'Approved' => 'success',
                                'Locked' => 'danger',
                                'Rejected' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('created_at')
                            ->label('Date Filed')
                            ->date('Y-m-d'),
                        TextEntry::make('title'),
                        TextEntry::make('requested_by')
                            ->label('Requested By')
                            ->state(function ($record) {
                                $parent = $record->parent;
                                $pr = $parent ? $parent->children()->where('module', 'purchase_request')->first() : null;
                                return $pr && $pr->requester ? $pr->requester->full_name : 'Not set';
                            }),
                        TextEntry::make('procurement_type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state)))
                            ->color(fn (string $state) => $state === 'small_value_procurement' ? 'info' : 'primary'),
                        TextEntry::make('fundCluster.name')
                            ->label('Fund Cluster'),
                        TextEntry::make('category.name')
                            ->label('Category'),
                        TextEntry::make('delivery_period_display')
                            ->label('Delivery Period')
                            ->state(function ($record) {
                                $parent = $record->parent;
                                $rfq = $parent ? $parent->children()->where('module', 'request_for_quotation')->first() : null;
                                if ($rfq && $rfq->delivery_mode === 'days' && $rfq->delivery_value) {
                                    return "Within {$rfq->delivery_value} calendar days upon receipt of Purchase Order";
                                }
                                if ($rfq && $rfq->delivery_mode === 'date' && $rfq->delivery_value) {
                                    return Carbon::parse($rfq->delivery_value)->format('F j, Y');
                                }
                                return 'Not set';
                            }),
                        TextEntry::make('deadline_date')
                            ->label('Submission Deadline')
                            ->state(function ($record) {
                                $parent = $record->parent;
                                $rfq = $parent ? $parent->children()->where('module', 'request_for_quotation')->first() : null;
                                return $rfq && $rfq->deadline_date instanceof \Carbon\Carbon
                                    ? $rfq->deadline_date->format('F j, Y, g:i A')
                                    : 'Not set';
                            }),
                    ])
                    ->columns(4),
                Section::make('Approval Stages')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(5)
                            ->schema([
                                TextEntry::make('hdr_procurement_id')
                                    ->label('')
                                    ->state('Procurement ID'),
                                TextEntry::make('hdr_approver')
                                    ->label('')
                                    ->state('Approver'),
                                TextEntry::make('hdr_sequence')
                                    ->label('')
                                    ->state('Sequence'),
                                TextEntry::make('hdr_status')
                                    ->label('')
                                    ->state('Status'),
                                TextEntry::make('hdr_remarks')
                                    ->label('')
                                    ->state('Remarks'),
                            ])
                            ->extraAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 border-b']),
                        RepeatableEntry::make('approvals')
                            ->label('')
                            ->schema([
                                TextEntry::make('procurement.procurement_id')->label(''),
                                TextEntry::make('employee.full_name')->label('')->default('N/A'),
                                TextEntry::make('sequence')->label('')->alignCenter(),
                                TextEntry::make('status')
                                    ->label('')
                                    ->formatStateUsing(function ($state) {
                                        return sprintf(
                                            '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium %s">%s</span>',
                                            match ($state) {
                                                'Approved' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                                                'Pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
                                                'Rejected' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
                                                default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100',
                                            },
                                            $state
                                        );
                                    })
                                    ->html(),
                                TextEntry::make('remarks')
                                    ->label('')
                                    ->default('N/A'),
                            ])
                            ->columns(5)
                            ->getStateUsing(function ($record) {
                                $approvals = $record->approvals()
                                    ->where('module', 'bac_resolution_recommending_award')
                                    ->with('employee')
                                    ->orderBy('sequence')
                                    ->get();
                                return $approvals->isEmpty() ? collect() : $approvals;
                            }),
                        TextEntry::make('no_approvers')
                            ->label('')
                            ->default('No approvers assigned.')
                            ->hidden(fn ($record) => $record->approvals()->where('module', 'bac_resolution_recommending_award')->count() > 0),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $employeeId = Auth::user()->employee->id ?? null;
        $isAssigned = $employeeId && $this->record->approvals()
            ->where('employee_id', $employeeId)
            ->where('status', 'Pending')
            ->exists();

        $canAct = $this->record->status === 'Locked' && $isAssigned;
        if ($canAct) {
            $currentApproval = $this->record->approvals()
                ->where('employee_id', $employeeId)
                ->where('status', 'Pending')
                ->first();
            if ($currentApproval) {
                $hasPreviousPending = $this->record->approvals()
                    ->where('module', 'bac_resolution_recommending_award')
                    ->where('sequence', '<', $currentApproval->sequence)
                    ->where('status', 'Pending')
                    ->exists();
                $hasRejection = $this->record->approvals()
                    ->where('module', 'bac_resolution_recommending_award')
                    ->where('status', 'Rejected')
                    ->exists();
                $canAct = !$hasPreviousPending && !$hasRejection;
            } else {
                $canAct = false;
            }
        }

        if (!$canAct) {
            return [
                Action::make('viewPdf')
                    ->label('View PDF')
                    ->icon('heroicon-o-document-text')
                    ->url(fn () => route('procurements.bac.pdf', $this->record), true)
                    ->color('info'),
            ];
        }

        return [
            Action::make('viewPdf')
                ->label('View PDF')
                ->icon('heroicon-o-document-text')
                ->url(fn () => route('procurements.bac.pdf', $this->record), true)
                ->color('info'),
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () use ($employeeId) {
                    $approval = $this->record->approvals()
                        ->where('employee_id', $employeeId)
                        ->where('status', 'Pending')
                        ->first();

                    if ($approval) {
                        $approval->update([
                            'status' => 'Approved',
                            'date_approved' => now(),
                            'remarks' => null,
                        ]);

                        $allApproved = $this->record->approvals()->where('module', 'bac_resolution_recommending_award')
                            ->where('status', 'Pending')
                            ->doesntExist();

                        if ($allApproved) {
                            $this->record->update(['status' => 'Approved']);
                        }
                        
                        ActivityLogger::log(
                'Approved BAC Resolution',
                'BAC Resolution ' . $this->record->procurement_id . ' was approved by ' . Auth::user()->name
            );

                        Notification::make()->title('BAC Resolution approved')->success()->send();
                        $this->record->refresh();
                    }
                }),
            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->form([
                    \Filament\Forms\Components\Textarea::make('remarks')
                        ->label('Remarks')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data) use ($employeeId) {
                    $approval = $this->record->approvals()
                        ->where('employee_id', $employeeId)
                        ->where('status', 'Pending')
                        ->first();

                    if ($approval) {
                        $approval->update([
                            'status' => 'Rejected',
                            'date_approved' => now(),
                            'remarks' => $data['remarks'],
                        ]);

                        $this->record->update(['status' => 'Rejected']);
                        $this->record->parent->update(['status' => 'Rejected']);

                       ActivityLogger::log(
                'Rejected BAC Resolution',
                'BAC Resolution ' . $this->record->procurement_id . ' was rejected by ' . Auth::user()->name .
                '. Remarks: ' . $data['remarks']
            );

                        Notification::make()->title('BAC Resolution rejected')->danger()->send();
                        $this->record->refresh();
                    }
                }),
        ];
    }
}