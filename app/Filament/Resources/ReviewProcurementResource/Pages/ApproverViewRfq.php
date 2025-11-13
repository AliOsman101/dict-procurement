<?php

namespace App\Filament\Resources\ReviewProcurementResource\Pages;

use App\Filament\Resources\ReviewProcurementResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use App\Models\Procurement;
use Carbon\Carbon;
use App\Helpers\ActivityLogger;

class ApproverViewRfq extends ViewRecord
{
    protected static string $resource = ReviewProcurementResource::class;
    protected static string $view = 'filament.resources.review-procurement-resource.pages.approver-view-rfq';

    public function mount($record): void
    {
        $child = Procurement::where('parent_id', $record)
            ->where('module', 'request_for_quotation')
            ->firstOrFail();

        $this->record = $child;
        $this->record->refresh();
        $this->record->load('approvals.employee');
    }

    public function getTitle(): string
    {
        return 'RFQ No. ' . ($this->record->procurement_id ?? 'Not set');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                Section::make('Request for Quotation Details')
                    ->schema([
                        TextEntry::make('procurement_id')->label('RFQ No.'),
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
                        TextEntry::make('requested_by')
                            ->label('Requested By')
                            ->getStateUsing(fn ($record) =>
                                $record->parent
                                    ?->children()
                                    ->where('module', 'purchase_request')
                                    ->first()
                                    ?->requester
                                    ?->full_name ?? 'Not set'
                            ),
                        TextEntry::make('procurement_type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state)))
                            ->color(fn ($state) => $state === 'small_value_procurement' ? 'info' : 'primary'),
                        TextEntry::make('fundCluster.name')->label('Fund Cluster')->default('Not set'),
                        TextEntry::make('category.name')->label('Category')->default('Not set'),
                        TextEntry::make('delivery_period_display')
                            ->label('Delivery Period')
                            ->state(fn ($record) => match (true) {
                                $record->delivery_mode === 'days' && $record->delivery_value => "Within {$record->delivery_value} calendar days upon receipt of Purchase Order",
                                $record->delivery_mode === 'date' && $record->delivery_value => Carbon::parse($record->delivery_value)->format('F j, Y'),
                                default => 'Not set',
                            }),
                        TextEntry::make('deadline_date')
                            ->label('Submission Deadline')
                            ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('F j, Y, g:i A') : 'Not set'),
                    ])
                    ->columns(4)
                    ->collapsible(),


                Section::make('Approval Stages')
                    ->schema([
                        Grid::make(5)->schema([
                            TextEntry::make('hdr_procurement_id')->label('')->state('Procurement ID'),
                            TextEntry::make('hdr_approver')->label('')->state('Approver'),
                            TextEntry::make('hdr_designation')->label('')->state('Designation'),
                            TextEntry::make('hdr_status')->label('')->state('Status'),
                            TextEntry::make('hdr_date_approved')->label('')->state('Date Approved'), // ← CHANGED
                        ])->extraAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 border-b']),

                        \Filament\Infolists\Components\RepeatableEntry::make('approvals')
                            ->label('')
                            ->schema([
                                TextEntry::make('procurement.procurement_id')->label('')->default('Not set'),
                                TextEntry::make('employee.full_name')->label('')->default('Not set'),
                                TextEntry::make('designation')
                                    ->label('')
                                    ->formatStateUsing(fn ($state, $record) => $record->module === 'request_for_quotation' && $record->procurement->office_section
                                        ? ($state ? "{$state} (" . ($record->procurement->office_section === 'DICT CAR - Admin and Finance Division' ? 'AFD' : 'TOD') . ")" : 'Not set')
                                        : ($state ?? 'Not set')
                                    )
                                    ->default('Not set'),
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
                                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('Y-m-d') : 'N/A'),
                            ])
                            ->columns(5)
                            ->getStateUsing(fn ($record) => $record->approvals()
                                ->where('module', 'request_for_quotation')
                                ->with('employee')
                                ->get()
                            ),
                        TextEntry::make('no_approvers')
                            ->label('')
                            ->default('No approvers assigned.')
                            ->hidden(fn ($record) => $record->approvals()->where('module', 'request_for_quotation')->count() > 0),
                    ])
                    ->collapsible(),
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
                    ->where('module', 'request_for_quotation')
                    ->where('sequence', '<', $currentApproval->sequence)
                    ->where('status', 'Pending')
                    ->exists();
                $hasRejection = $this->record->approvals()
                    ->where('module', 'request_for_quotation')
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
                ->url(fn () => route('procurements.rfq.pdf', $this->record->parent_id), true)
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

                        $allApproved = $this->record->approvals()
                            ->where('module', 'request_for_quotation')
                            ->where('status', 'Pending')
                            ->doesntExist();

                        if ($allApproved) {
                            $this->record->update(['status' => 'Approved']);
                        }

                        ActivityLogger::log(
                            'Approved Request for Quotation',
                            "RFQ {$this->record->procurement_id} approved by " . auth()->user()->name
                        );

                          // 🔔 Send Gmail notifications
                        $procurement = $this->record;
                        $approver = auth()->user();
                        $employees = $procurement->employees ?? collect();
                        $creator = $procurement->creator ?? $procurement->requester ?? $procurement->parent?->requester ?? null;

                        foreach ($employees as $employee) {
                            if ($employee->email) {
                                \Mail::to($employee->email)->send(
                                    new \App\Mail\RequestForQuotationStatusMail($procurement, 'Approved', $approver)
                                );
                            }
                        }

                        if ($creator && $creator->email) {
                            \Mail::to($creator->email)->send(
                                new \App\Mail\RequestForQuotationStatusMail($procurement, 'Approved', $approver)
                            );
                        }

                        Notification::make()->title('RFQ approved')->success()->send();
                        $this->record->refresh();
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

                        $this->record->update(['status' => 'Rejected']);
                        $this->record->parent->update(['status' => 'Rejected']);

                        ActivityLogger::log(
                            'Rejected Request for Quotation',
                            "RFQ {$this->record->procurement_id} rejected by " . auth()->user()->name . ": {$data['remarks']}"
                        );

                        $this->sendStatusEmail('Rejected', $data['remarks']);
                        Notification::make()->title('RFQ rejected')->danger()->send();
                        $this->record->refresh();
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
        $creator = $procurement->creator ?? $procurement->requester ?? $procurement->parent?->requester ?? null;

        foreach ($employees as $employee) {
            if ($employee->email) {
                \Mail::to($employee->email)->send(
                    new \App\Mail\RequestForQuotationStatusMail($procurement, $status, $approver, $remarks)
                );
            }
        }

        if ($creator && $creator->email) {
            \Mail::to($creator->email)->send(
                new \App\Mail\RequestForQuotationStatusMail($procurement, $status, $approver, $remarks)
            );
        }
    }

    public function getRelationManagers(): array
    {
        return [];
    }
}