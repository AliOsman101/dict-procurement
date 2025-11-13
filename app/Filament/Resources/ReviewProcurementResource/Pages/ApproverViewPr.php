<?php

namespace App\Filament\Resources\ReviewProcurementResource\Pages;

use App\Filament\Resources\ReviewProcurementResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Grid;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use App\Models\Procurement;
use App\Helpers\ActivityLogger;
use Carbon\Carbon;

class ApproverViewPr extends ViewRecord
{
    protected static string $resource = ReviewProcurementResource::class;

    public function mount($record): void
    {
        $child = Procurement::where('parent_id', $record)
                            ->where('module', 'purchase_request')
                            ->firstOrFail();
        $this->record = $child;
        $this->record->refresh();
        $this->record->load('approvals.employee');
    }

    public function getTitle(): string
    {
        return "PR No. " . ($this->record->procurement_id ?? 'N/A');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Purchase Request Details')
                    ->schema([
                        TextEntry::make('procurement_id')->label('PR No.'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'Pending' => 'warning',
                                'Approved' => 'success',
                                'Locked' => 'danger',
                                'Rejected' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('created_at')->label('Date Filed')->date('Y-m-d'),
                        TextEntry::make('title'),
                        TextEntry::make('requester.full_name')->label('Requested By')->default('Not set'),
                        TextEntry::make('procurement_type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state)))
                            ->color(fn ($state) => $state === 'small_value_procurement' ? 'info' : 'primary'),
                        TextEntry::make('fundCluster.name')->label('Fund Cluster'),
                        TextEntry::make('category.name')->label('Category'),
                    ])
                    ->columns(4),

                Section::make('Approval Stages')
                    ->schema([
                        Grid::make(5)
                            ->schema([
                                TextEntry::make('hdr_procurement_id')->label('')->state('Procurement ID'),
                                TextEntry::make('hdr_approver')->label('')->state('Approver'),
                                TextEntry::make('hdr_sequence')->label('')->state('Sequence'),
                                TextEntry::make('hdr_status')->label('')->state('Status'),
                                TextEntry::make('hdr_date_approved')->label('')->state('Date Approved'), // ← CHANGED
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
                                    ->html()
                                    ->formatStateUsing(fn ($state) => sprintf(
                                        '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium %s">%s</span>',
                                        match ($state) {
                                            'Approved' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                                            'Pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
                                            'Rejected' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
                                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100',
                                        },
                                        $state
                                    )),
                                TextEntry::make('date_approved') // ← CHANGED FROM `remarks`
                                    ->label('')
                                    ->default('N/A')
                                    ->formatStateUsing(fn ($state) => $state && $state !== 'N/A' ? Carbon::parse($state)->format('Y-m-d') : 'N/A'),
                            ])
                            ->columns(5)
                            ->getStateUsing(fn ($record) => $record->approvals()
                                ->where('module', 'purchase_request')
                                ->with('employee')
                                ->orderBy('sequence')
                                ->get()
                            ),

                        TextEntry::make('no_approvers')
                            ->label('')
                            ->default('No approvers assigned.')
                            ->hidden(fn ($record) => $record->approvals()->where('module', 'purchase_request')->count() > 0),
                    ]),

                Section::make('Item Details')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('unit')->label('Unit'),
                                TextEntry::make('item_description')->label('Description'),
                                TextEntry::make('quantity')->label('Qty'),
                                TextEntry::make('unit_cost')->label('Unit Cost')->money('PHP'),
                                TextEntry::make('total_cost')->label('Total Cost')->money('PHP'),
                            ])
                            ->columns(5)
                            ->columnSpanFull(),
                        TextEntry::make('grand_total')
                            ->label('Grand Total')
                            ->money('PHP')
                            ->extraAttributes(['class' => 'font-bold text-lg text-right mt-2']),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
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
                    ->where('module', 'purchase_request')
                    ->where('sequence', '<', $currentApproval->sequence)
                    ->where('status', 'Pending')
                    ->exists();
                $hasRejection = $this->record->approvals()
                    ->where('module', 'purchase_request')
                    ->where('status', 'Rejected')
                    ->exists();
                $canAct = !$hasPreviousPending && !$hasRejection;
            } else {
                $canAct = false;
            }
        }

        $actions = [
            Actions\Action::make('viewPdf')
                ->label('View PDF')
                ->icon('heroicon-o-document-text')
                ->url(fn () => route('procurements.pr.pdf', $this->record), true)
                ->color('info'),
        ];

        if ($canAct) {
            $actions[] = Actions\Action::make('approve')
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

                        ActivityLogger::log(
                            'Approved Purchase Request',
                            "PR {$this->record->procurement_id} approved by " . Auth::user()->name
                        );

                        $allApproved = $this->record->approvals()
                            ->where('module', 'purchase_request')
                            ->where('status', 'Pending')
                            ->doesntExist();

                        if ($allApproved) {
                            $this->record->update(['status' => 'Approved']);
                        }

                        $this->sendStatusEmail('Approved');
                        $this->record->refresh();
                        Notification::make()->title('PR approved')->success()->send();
                    }
                });

            $actions[] = Actions\Action::make('reject')
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

                        ActivityLogger::log(
                            'Rejected Purchase Request',
                            "PR {$this->record->procurement_id} rejected by " . Auth::user()->name . ": {$data['remarks']}"
                        );

                        $this->record->update(['status' => 'Rejected']);
                        if ($this->record->parent) {
                            $this->record->parent->update(['status' => 'Rejected']);
                        }

                        $this->sendStatusEmail('Rejected', $data['remarks']);
                        $this->record->refresh();
                        Notification::make()->title('PR rejected')->danger()->send();
                    }
                });
        }

        return $actions;
    }

    private function sendStatusEmail(string $status, ?string $remarks = null): void
    {
        $procurement = $this->record;
        $approver = auth()->user();
        $employees = $procurement->employees ?? collect();
        $creator = $procurement->creator ?? $procurement->requester ?? null;

        foreach ($employees as $employee) {
            if ($employee->email) {
                \Mail::to($employee->email)->send(
                    new \App\Mail\PurchaseRequestStatusMail($procurement, $status, $approver, $remarks)
                );
            }
        }

        if ($creator && $creator->email) {
            \Mail::to($creator->email)->send(
                new \App\Mail\PurchaseRequestStatusMail($procurement, $status, $approver, $remarks)
            );
        }
    }
}