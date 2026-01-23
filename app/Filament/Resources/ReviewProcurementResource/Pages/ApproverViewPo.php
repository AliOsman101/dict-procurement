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
use App\Mail\PurchaseOrderLockedMail;
use App\Mail\NextApproverNotificationMail;

class ApproverViewPo extends ViewRecord
{
    protected static string $resource = ReviewProcurementResource::class;

    public function mount($record): void
    {
        $child = Procurement::where('parent_id', $record)
            ->where('module', 'purchase_order')
            ->firstOrFail();

        $this->record = $child;
        $this->record->refresh();
        $this->record->load('approvals.employee');
    }

    public function getTitle(): string
    {
        return "PO No. " . ($this->record->procurement_id ?? 'N/A');
    }

    protected function hasBacApproved(): bool
    {
        if (!$this->record->parent_id) return false;

        $parent = Procurement::find($this->record->parent_id);
        if (!$parent) return false;

        $bacChild = $parent->children()
            ->where('module', 'bac_resolution_recommending_award')
            ->first();

        return $bacChild && $bacChild->status === 'Approved';
    }

    protected function isFullyApproved(): bool
    {
        $approvals = $this->record->approvals()
            ->where('module', 'purchase_order')
            ->get();

        return $approvals->isNotEmpty()
            && $approvals->every(fn ($a) => $a->status === 'Approved');
    }

    protected function isRejected(): bool
    {
        $approvals = $this->record->approvals()
            ->where('module', 'purchase_order')
            ->get();

        return $approvals->contains('status', 'Rejected');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        // Get rejection details if rejected
        $rejectionApproval = null;
        if ($this->isRejected()) {
            $rejectionApproval = $this->record->approvals()
                ->where('module', 'purchase_order')
                ->where('status', 'Rejected')
                ->with('employee')
                ->orderBy('action_at', 'desc')
                ->first();
        }

        $schema = [];

        // Add rejection notice section if rejected
        if ($rejectionApproval) {
            $schema[] = Section::make('PO Rejected')
                ->schema([
                    TextEntry::make('rejection_remarks')
                        ->label('Rejection Remarks')
                        ->state($rejectionApproval->remarks ?? 'No remarks provided')
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->extraAttributes(['class' => 'bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500']);
        }

        // Add main sections
        $schema[] = Section::make('Purchase Order Details')
            ->schema([
                TextEntry::make('procurement_id')->label('PO No.'),
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
                        $approvals = $record->approvals()->where('module', 'purchase_order')->get();
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

                // PO Details with visual feedback
                TextEntry::make('place_of_delivery')
                    ->label('Place of Delivery')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => $state ?: 'Not set'),

                TextEntry::make('date_of_delivery')
                    ->label('Date of Delivery')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => $state && $state !== 'N/A' ? Carbon::parse($state)->format('Y-m-d') : 'N/A'),

                TextEntry::make('payment_term')
                    ->label('Payment Term')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => $state ?: 'Not set'),

                TextEntry::make('ors_burs_no')
                    ->label('ORS/BURS No.')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => $state ?: 'Not set'),

                TextEntry::make('ors_burs_date')
                    ->label('Date of ORS/BURS')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => $state && $state !== 'N/A' ? Carbon::parse($state)->format('Y-m-d') : 'N/A'),
            ])
            ->columns(4);

        $schema[] = Section::make('Approval Stages')
            ->schema([
                Grid::make(5)
                    ->schema([
                        TextEntry::make('hdr_procurement_id')->label('')->state('Procurement ID'),
                        TextEntry::make('hdr_approver')->label('')->state('Approver'),
                        TextEntry::make('hdr_sequence')->label('')->state('Sequence'),
                        TextEntry::make('hdr_status')->label('')->state('Status'),
                        TextEntry::make('hdr_action_date')->label('')->state('Action Date'),
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
                        TextEntry::make('action_at')
                            ->label('')
                            ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('M d, Y') : '—')
                            ->color(fn ($record) => $record->status === 'Rejected' ? 'danger' 
                                                : ($record->status === 'Approved' ? 'success' : 'gray'))
                            ->icon(fn ($record) => $record->status === 'Approved' ? 'heroicon-o-check-circle'
                                                : ($record->status === 'Rejected' ? 'heroicon-o-x-circle' : '')),
                    ])
                    ->columns(5)
                    ->getStateUsing(fn ($record) => $record->approvals()
                        ->where('module', 'purchase_order')
                        ->with('employee')
                        ->orderBy('sequence')
                        ->get()
                    ),

                TextEntry::make('no_approvers')
                    ->label('')
                    ->default('No approvers assigned.')
                    ->hidden(fn ($record) => $record->approvals()
                        ->where('module', 'purchase_order')
                        ->count() > 0),
            ]);

        return $infolist->schema($schema);
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
                    ->where('module', 'purchase_order')
                    ->where('sequence', '<', $currentApproval->sequence)
                    ->where('status', 'Pending')
                    ->exists();
                $hasRejection = $this->record->approvals()
                    ->where('module', 'purchase_order')
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
                ->url(fn () => route('procurements.po.pdf', $this->record->id), true)
                ->color('info'),
        ];

     if ($canAct) {

    /*
    |--------------------------------------------------------------------------
    | APPROVE PURCHASE ORDER
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

            if ($approval) {

                // ✔ Update approval row
                $approval->update([
                    'status' => 'Approved',
                    'action_at' => now(),
                    'remarks' => null,
                ]);

                /*
                /*
/*
|--------------------------------------------------------------------------
| EMAIL NOTIFICATION TO REQUESTER & PR EMPLOYEES (FIXED)
|--------------------------------------------------------------------------
*/

// 1️⃣ Requester (must check user->email)
$requesterUser = $this->record->parent?->requester?->user;

if ($requesterUser && !empty($requesterUser->email)) {
    \Mail::to($requesterUser->email)->send(
        new \App\Mail\PurchaseOrderStatusMail(
            $this->record,
            'Approved',
            Auth::user(),
            null
        )
    );
}

// 2️⃣ PR Employees (corrected to use employee->user->email)
$parent = $this->record->parent;

if ($parent && $parent->employees) {
    foreach ($parent->employees as $employee) {

        $user = $employee->user ?? null;  // FIX

        if ($user && !empty($user->email)) {
            \Mail::to($user->email)->send(
                new \App\Mail\PurchaseOrderStatusMail(
                    $this->record,
                    'Approved',
                    Auth::user(),
                    null
                )
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| NEXT APPROVER EMAIL NOTIFICATION (NO CHANGE)
|--------------------------------------------------------------------------
*/
$nextApproval = $this->record->approvals()
    ->where('module', 'purchase_order')
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
        \Log::error("FAILED TO SEND NEXT APPROVER PO EMAIL: {$e->getMessage()}");
    }
}


                /*
                |--------------------------------------------------------------------------
                | CHECK IF ALL APPROVED
                |--------------------------------------------------------------------------
                */
                $allApproved = $this->record->approvals()
                    ->where('module', 'purchase_order')
                    ->where('status', 'Pending')
                    ->doesntExist();

                if ($allApproved) {
                    $this->record->update(['status' => 'Approved']);
                }

                /*
                |--------------------------------------------------------------------------
                | UPDATE PARENT STATUS
                |--------------------------------------------------------------------------
                */
                if ($this->record->parent_id) {
                    $parent = Procurement::find($this->record->parent_id);
                    if ($parent) {
                        \App\Helpers\ProcurementStatusHelper::updateParentStatus($parent);
                    }
                }

                ActivityLogger::log(
                    'Approved Purchase Order',
                    "PO {$this->record->procurement_id} approved by " . Auth::user()->name
                );

                Notification::make()->title('PO approved')->success()->send();
                $this->record->refresh();
            }
        });



    /*
    |--------------------------------------------------------------------------
    | REJECT PURCHASE ORDER
    |--------------------------------------------------------------------------
    */
    $actions[] = Action::make('reject')
        ->label('Reject')
        ->icon('heroicon-o-x-mark')
        ->color('danger')
        ->form([
            \Filament\Forms\Components\Textarea::make('remarks')
                ->label('Rejection Remarks')
                ->placeholder('Please provide a reason for rejecting this Purchase Order...')
                ->required()
                ->rows(4)
                ->maxLength(500),
        ])
        ->modalHeading('Reject Purchase Order')
        ->modalDescription('Please provide detailed remarks explaining why this Purchase Order is being rejected.')
        ->modalSubmitActionLabel('Reject PO')
        ->modalIcon('heroicon-o-x-circle')
        ->modalIconColor('danger')
        ->action(function (array $data) use ($employeeId) {

            $approval = $this->record->approvals()
                ->where('employee_id', $employeeId)
                ->where('status', 'Pending')
                ->first();

            if ($approval) {

                // ✔ Update approval row
                $approval->update([
                    'status' => 'Rejected',
                    'action_at' => now(),
                    'remarks' => $data['remarks'],
                ]);

                /*
                |--------------------------------------------------------------------------
                | EMAIL NOTIFICATION (PO REJECTED) — your existing logic
                |--------------------------------------------------------------------------
                */
                $recipientEmail = $this->record->parent?->requester?->email;

                if (!empty($recipientEmail)) {
                    \Mail::to($recipientEmail)->send(
                        new \App\Mail\PurchaseOrderStatusMail(
                            $this->record,
                            'Rejected',
                            Auth::user(),
                            $data['remarks']
                        )
                    );
                }

                // ✔ Module status
                $this->record->update(['status' => 'Rejected']);

                // ✔ Parent status
                if ($this->record->parent_id) {
                    $parent = Procurement::find($this->record->parent_id);
                    if ($parent) {
                        $parent->update(['status' => 'Rejected']);
                    }
                }

                ActivityLogger::log(
                    'Rejected Purchase Order',
                    "PO {$this->record->procurement_id} rejected by " . Auth::user()->name . ": {$data['remarks']}"
                );

                Notification::make()->title('PO rejected')->danger()->send();
                $this->record->refresh();
            }
        });
}

return $actions;

    }
}