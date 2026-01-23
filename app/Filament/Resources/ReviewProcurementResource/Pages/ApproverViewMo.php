<?php

namespace App\Filament\Resources\ReviewProcurementResource\Pages;

use App\Filament\Resources\ReviewProcurementResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Procurement;
use App\Helpers\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Mail\NextApproverNotificationMail;
use App\Mail\MinutesOfOpeningStatusMail;


class ApproverViewMo extends ViewRecord
{
    protected static string $resource = ReviewProcurementResource::class;

    public function mount($record): void
    {
        $child = Procurement::where('parent_id', $record)
            ->where('module', 'minutes_of_opening')
            ->firstOrFail();

        $this->record = $child;
        $this->record->refresh();
        $this->record->load('approvals.employee');
    }

    public function getTitle(): string
    {
        return "Minutes of Opening No. " . ($this->record->procurement_id ?? 'N/A');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Minutes of Opening Details')
                ->schema([
                    TextEntry::make('procurement_id')->label('Minutes No.'),
                    TextEntry::make('status')
                        ->badge()
                        ->color(fn ($state) => match ($state) {
                            'Pending'  => 'warning',
                            'Approved' => 'success',
                            'Locked'   => 'danger',
                            'Rejected' => 'danger',
                            default    => 'gray',
                        })
                        ->getStateUsing(function ($record) {
                            $approvals = $record->approvals()
                                ->where('module', 'minutes_of_opening')
                                ->get();

                            if ($approvals->isEmpty()) return 'Pending';
                            if ($approvals->contains('status', 'Rejected')) return 'Rejected';
                            if ($approvals->every(fn ($a) => $a->status === 'Approved')) return 'Approved';
                            return $record->status;
                        }),
                    TextEntry::make('created_at')->label('Date Filed')->date('F j, Y'),
                    TextEntry::make('title')->label('Project Title'),
                    TextEntry::make('requested_by')
                        ->label('Requested By')
                        ->getStateUsing(fn ($record) => $record->parent
                            ?->children()
                            ->where('module', 'purchase_request')
                            ->first()
                            ?->requester
                            ?->full_name ?? 'Not set'
                        ),
                    TextEntry::make('fundCluster.name')->label('Fund Cluster'),
                    TextEntry::make('category.name')->label('Category'),
                    TextEntry::make('bid_opening_datetime')
                        ->label('Bid Opening Date & Time')
                        ->formatStateUsing(fn ($state) => $state?->format('F j, Y - h:i A') ?? 'Not set'),
                ])
                ->columns(4),

            Section::make('Approval Stages')
                ->schema([
                    Grid::make(5)
                        ->schema([
                            TextEntry::make('hdr_procurement_id')
                                ->label('')
                                ->state('Procurement ID')
                                ->weight('bold'),
                            TextEntry::make('hdr_approver')
                                ->label('')
                                ->state('Approver')
                                ->weight('bold'),
                            TextEntry::make('hdr_sequence')
                                ->label('')
                                ->state('Sequence')
                                ->weight('bold'),
                            TextEntry::make('hdr_status')
                                ->label('')
                                ->state('Status')
                                ->weight('bold'),
                            TextEntry::make('hdr_date')
                                ->label('')
                                ->state('Action Date')
                                ->weight('bold'),
                        ])
                        ->extraAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 border-b']),

                    RepeatableEntry::make('approvals')
                        ->label('')
                        ->schema([
                            TextEntry::make('procurement.procurement_id')->label(''),
                            TextEntry::make('employee.full_name')
                                ->label('')
                                ->default('N/A'),
                            TextEntry::make('sequence')
                                ->label('')
                                ->alignCenter(),
                            TextEntry::make('status')
                                ->label('')
                                ->html()
                                ->formatStateUsing(fn ($state) => sprintf(
                                    '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium %s">%s</span>',
                                    match ($state) {
                                        'Approved' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                                        'Pending'  => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
                                        'Rejected' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
                                        default    => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100',
                                    },
                                    $state
                                )),
                            TextEntry::make('action_at')
                                ->label('')
                                ->getStateUsing(fn ($record) => $record->action_at?->format('M d, Y') ?? '—')
                                ->color(fn ($record) => $record->status === 'Rejected' ? 'danger' : ($record->status === 'Approved' ? 'success' : 'gray'))
                                ->icon(fn ($record) => $record->status === 'Approved' ? 'heroicon-o-check-circle' : ($record->status === 'Rejected' ? 'heroicon-o-x-circle' : '')),
                        ])
                        ->columns(5)
                        ->getStateUsing(fn () => $this->record->approvals()
                            ->where('module', 'minutes_of_opening')
                            ->with('employee')
                            ->orderBy('sequence')
                            ->get()
                        ),

                    TextEntry::make('no_approvers')
                        ->label('')
                        ->state('No approvers assigned.')
                        ->hidden(fn () => $this->record->approvals()
                            ->where('module', 'minutes_of_opening')
                            ->exists()
                        ),
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
                    ->where('sequence', '<', $currentApproval->sequence)
                    ->where('status', 'Pending')
                    ->exists();

                $canAct = !$hasPreviousPending;
            } else {
                $canAct = false;
            }
        }

        $actions = [
            Action::make('viewPdf')
                ->label('View PDF')
                ->icon('heroicon-o-document-text')
                ->url(fn () => route('procurements.mo.pdf', $this->record->parent->id), true)
                ->color('info'),
        ];

        if ($canAct) {

    /*
    |--------------------------------------------------------------------------
    | APPROVE Minutes of Opening
    |--------------------------------------------------------------------------
    */
    $actions[] = Action::make('approve')
        ->label('Approve')
        ->icon('heroicon-o-check')
        ->color('success')
        ->requiresConfirmation()
        ->action(function () use ($employeeId) {

            $approval = $this->record->approvals()
                ->where('employee_id', $employeeId)
                ->where('status', 'Pending')
                ->first();

            if (!$approval) {
                return;
            }

            // ✔ Update approval row
            $approval->update([
                'status' => 'Approved',
                'action_at' => now(),
            ]);

            // ✔ If no remaining pending approvers → set module as Approved
            $allApproved = $this->record->approvals()
                ->where('module', 'minutes_of_opening')
                ->where('status', 'Pending')
                ->doesntExist();


            if ($allApproved) {
                $this->record->update(['status' => 'Approved']);
            }

            $this->sendMinutesEmail('Approved');

            ActivityLogger::log(
                'Approved Minutes of Opening',
                "MO {$this->record->procurement_id} approved by " . auth()->user()->name
            );

            /*
            |--------------------------------------------------------------------------
            | NEXT APPROVER EMAIL NOTIFICATION (NEW)
            |--------------------------------------------------------------------------
            */
            $nextApproval = $this->record->approvals()
                ->where('module', 'minutes_of_opening')
                ->where('sequence', '>', $approval->sequence)
                ->where('status', 'Pending')
                ->orderBy('sequence')
                ->first();

            if ($nextApproval && $nextApproval->employee?->user?->email) {
                try {
                    \Mail::to($nextApproval->employee->user->email)
                        ->send(new \App\Mail\NextApproverNotificationMail(
                            $this->record,
                            $nextApproval->employee->full_name,
                            $nextApproval->sequence
                        ));
                } catch (\Exception $e) {
                    \Log::error("FAILED TO SEND NEXT MO APPROVER EMAIL: {$e->getMessage()}");
                }
            }

            Notification::make()
                ->success()
                ->title('Minutes of Opening Approved')
                ->send();

            $this->record->refresh();
        });



    /*
    |--------------------------------------------------------------------------
    | REJECT Minutes of Opening
    |--------------------------------------------------------------------------
    */
    $actions[] = Action::make('reject')
        ->label('Reject')
        ->icon('heroicon-o-x-mark')
        ->color('danger')
        ->requiresConfirmation()
        ->form([
            \Filament\Forms\Components\Textarea::make('remarks')
                ->label('Reason for Rejection')
                ->required()
                ->rows(4),
        ])
        ->action(function ($data) use ($employeeId) {

            $approval = $this->record->approvals()
                ->where('employee_id', $employeeId)
                ->where('status', 'Pending')
                ->first();

            if (!$approval) {
                return;
            }

            // ✔ Update rejection
            $approval->update([
                'status' => 'Rejected',
                'remarks' => $data['remarks'],
                'action_at' => now(),
            ]);

            // ✔ Update module status
            $this->record->update(['status' => 'Rejected']);

            $this->sendMinutesEmail('Rejected', $data['remarks']);

            ActivityLogger::log(
                'Rejected Minutes of Opening',
                "MO {$this->record->procurement_id} rejected by " . auth()->user()->name .
                " with remarks: {$data['remarks']}"
            );

            Notification::make()
                ->danger()
                ->title('Minutes of Opening Rejected')
                ->send();

            $this->record->refresh();
        });
}

return $actions;

    }
    private function sendMinutesEmail(string $status, ?string $remarks = null): void
{
    $procurement = $this->record;
    $parent = $procurement->parent;

    if (!$parent) return;

    $creator = $parent->creator ?? $parent->requester ?? null;
    $link = url('/admin/procurements/' . $parent->id);

    // 1. Send to requester
    if ($creator && $creator->email) {
        \Mail::to($creator->email)->send(
            new \App\Mail\MinutesOfOpeningStatusMail(
                $procurement,
                $creator,
                $status,
                $remarks,
                $link
            )
        );
    }

    // 2. Send to PR employees
    foreach ($parent->employees as $employee) {
        if (!empty($employee->email)) {
            \Mail::to($employee->email)->send(
                new \App\Mail\MinutesOfOpeningStatusMail(
                    $procurement,
                    $employee,
                    $status,
                    $remarks,
                    $link
                )
            );
        }
    }
}
}