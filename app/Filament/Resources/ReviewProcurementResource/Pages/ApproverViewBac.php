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
use Illuminate\Support\Facades\Auth;
use App\Models\Procurement;
use Carbon\Carbon;
use App\Helpers\ActivityLogger;
use Illuminate\Support\Facades\Mail;
use App\Mail\BacStatusMail;
use App\Mail\NextApproverNotificationMail;

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
        $this->record->load('approvals.employee');
    }

    public function getTitle(): string
    {
        return "BAC Resolution No. " . ($this->record->procurement_id ?? 'N/A');
    }

    protected function isLocked(): bool
    {
        return $this->record->status === 'Locked';
    }

    protected function isFullyApproved(): bool
    {
        $approvals = $this->record->approvals()
            ->where('module', 'bac_resolution_recommending_award')
            ->get();

        return $approvals->isNotEmpty()
            && $approvals->every(fn ($a) => $a->status === 'Approved');
    }

    protected function hasAoqApproved(): bool
    {
        if (!$this->record->parent_id) return false;

        $parent = Procurement::find($this->record->parent_id);
        if (!$parent) return false;

        $aoqChild = $parent->children()
            ->where('module', 'abstract_of_quotation')
            ->first();

        if (!$aoqChild) return false;

        $approvals = $aoqChild->approvals()
            ->where('module', 'abstract_of_quotation')
            ->get();

        return $approvals->isNotEmpty()
            && $approvals->every(fn ($approval) => $approval->status === 'Approved');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('BAC Resolution Details')
                    ->schema([
                        TextEntry::make('procurement_id')->label('BAC Resolution No.'),
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
                                    ->where('module', 'bac_resolution_recommending_award')
                                    ->get();

                                if ($approvals->isEmpty()) return 'Pending';
                                if ($approvals->contains('status', 'Rejected')) return 'Rejected';
                                if ($approvals->every(fn ($a) => $a->status === 'Approved')) return 'Approved';
                                return $record->status;
                            }),
                        TextEntry::make('created_at')->label('Date Filed')->date('Y-m-d'),
                        TextEntry::make('title'),
                        TextEntry::make('requested_by')
                            ->label('Requested By')
                            ->getStateUsing(fn ($record) => $record->parent
                                ?->children()
                                ->where('module', 'purchase_request')
                                ->first()
                                ?->requester
                                ?->full_name ?? 'Not set'
                            ),
                        TextEntry::make('procurement_type')
                            ->badge()
                            ->color(fn ($state) => $state === 'small_value_procurement' ? 'info' : 'primary')
                            ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state))),
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
                                            'Pending'  => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
                                            'Rejected' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
                                            default    => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100',
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
                                ->where('module', 'bac_resolution_recommending_award')
                                ->with('employee')
                                ->orderBy('sequence')
                                ->get()
                            ),

                        TextEntry::make('no_approvers')
                            ->label('')
                            ->default('No approvers assigned.')
                            ->hidden(fn ($record) => $record->approvals()
                                ->where('module', 'bac_resolution_recommending_award')
                                ->count() > 0),
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

        $actions = [
            Action::make('viewPdf')
                ->label('View PDF')
                ->icon('heroicon-o-document-text')
                ->url(fn () => route('procurements.bac.pdf', $this->record), true)
                ->color('info'),
        ];

        if ($canAct) {

    /*
    |--------------------------------------------------------------------------
    | APPROVE BAC RESOLUTION
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

            // ✔ Update this approver's approval row
            $approval->update([
                'status' => 'Approved',
                'date_approved' => now(),
                'remarks' => null,
            ]);

            // ✔ Check if all BAC approvers are done
            $allApproved = !$this->record->bacApprovals()
    ->where('status', 'Pending')
    ->exists();

            if ($allApproved) {
    // all approved
    $this->record->update(['status' => 'Approved']);
    $this->sendBacStatusEmail('Approved');
} else {
    // at least 1 approved
    $this->sendBacStatusEmail('Approved');
}


            // ✔ Log
            ActivityLogger::log(
                'Approved BAC Resolution',
                "BAC Resolution {$this->record->procurement_id} approved by " . Auth::user()->name
            );

            /*
            |--------------------------------------------------------------------------
            | NEXT APPROVER EMAIL NOTIFICATION (NEW)
            |--------------------------------------------------------------------------
            */
            $nextApproval = $this->record->approvals()
                ->where('module', 'bac_resolution_recommending_award')
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
                    \Log::error("FAILED TO SEND NEXT BAC APPROVER EMAIL: {$e->getMessage()}");
                }
            }

            // ✔ Notification
            Notification::make()
                ->title('BAC Resolution approved')
                ->success()
                ->send();

            $this->record->refresh();
        });



    /*
    |--------------------------------------------------------------------------
    | REJECT BAC RESOLUTION
    |--------------------------------------------------------------------------
    */
    $actions[] = Action::make('reject')
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

            if (!$approval) {
                return;
            }

            // ✔ Update approval row
            $approval->update([
                'status' => 'Rejected',
                'date_approved' => now(),
                'remarks' => $data['remarks'],
            ]);

            // ✔ Reject the module AND its parent
            $this->record->update(['status' => 'Rejected']);
            $this->record->parent()->update(['status' => 'Rejected']);

            // ✔ Email notification (existing)
            $this->sendBacStatusEmail('Rejected', $data['remarks']);

            // ✔ Log
            ActivityLogger::log(
                'Rejected BAC Resolution',
                "BAC Resolution {$this->record->procurement_id} rejected by " . Auth::user()->name .
                ": {$data['remarks']}"
            );

            // ✔ Alert
            Notification::make()
                ->title('BAC Resolution rejected')
                ->danger()
                ->send();

            $this->record->refresh();
        });
}

return $actions;

    }
    private function sendBacStatusEmail(string $status, ?string $remarks = null): void
{
    $procurement = $this->record; // BAC child
    $approver = auth()->user();

    // Get PR (root)
    $pr = $procurement->parent;

    if (! $pr) {
        return;
    }

    // RECIPIENT 1: PR creator (MOST IMPORTANT)
    $creator = $pr->creator ?? $pr->requester ?? null;

    if ($creator && $creator->email) {
        Mail::to($creator->email)->send(
            new BacStatusMail($procurement, $approver, $status, $remarks)
        );
    }

    // RECIPIENT 2: Employees assigned in PR
    $prEmployees = $pr->employees ?? collect();

    foreach ($prEmployees as $employee) {
        if ($employee->email) {
            Mail::to($employee->email)->send(
                new BacStatusMail($procurement, $approver, $status, $remarks)
            );
        }
    }
}
}