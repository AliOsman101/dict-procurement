<?php

namespace App\Filament\Resources\ProcurementResource\Pages;

use App\Filament\Resources\ProcurementResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Forms;
use App\Models\Procurement;
use App\Helpers\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ViewPo extends ViewRecord
{
    protected static string $resource = ProcurementResource::class;

    public function mount($record): void
    {
        $child = Procurement::where('parent_id', $record)
                            ->where('module', 'purchase_order')
                            ->firstOrFail();
        $this->record = $child;
        $this->record->refresh();
    }

    public function getTitle(): string
    {
        return "PO No. " . ($this->record->procurement_id ?? 'N/A');
    }

    // Check if BAC Resolution is approved
    protected function hasBacApproved(): bool
    {
        if (!$this->record->parent_id) {
            return false;
        }

        $parent = Procurement::find($this->record->parent_id);
        
        if (!$parent) {
            return false;
        }
        
        // Check if BAC Resolution child record exists and is approved
        $bacChild = $parent->children()->where('module', 'bac_resolution_recommending_award')->first();
        
        if ($bacChild) {
            return $bacChild->status === 'Approved';
        }

        return false;
    }

    // Get the first missing requirement in the procurement chain
    protected function getFirstMissingRequirement(): ?array
    {
        if (!$this->record->parent_id) {
            return null;
        }

        $parent = Procurement::find($this->record->parent_id);
        if (!$parent) return null;

        // 1. Check PPMP
        $ppmpChild = $parent->children()->where('module', 'ppmp')->first();
        if (!$ppmpChild || !$ppmpChild->documents()->where('module', 'ppmp')->exists()) {
            return [
                'title' => 'PPMP Required',
                'message' => 'You must upload a <strong class="text-danger-600 dark:text-danger-400 font-semibold">PPMP document</strong> first before proceeding with this Purchase Order.',
                'url' => route('filament.admin.resources.procurements.view-ppmp', $parent->id),
                'buttonLabel' => 'Go to PPMP'
            ];
        }

        // 2. Check PR Approved
        $prChild = $parent->children()->where('module', 'purchase_request')->first();
        if (!$prChild || $prChild->status !== 'Approved') {
            return [
                'title' => 'PR Approval Required',
                'message' => 'The <strong class="text-danger-600 dark:text-danger-400 font-semibold">Purchase Request must be approved</strong> first before proceeding with this Purchase Order.',
                'url' => route('filament.admin.resources.procurements.view-pr', $parent->id),
                'buttonLabel' => 'Go to PR'
            ];
        }

        // 3. Check RFQ Approved
        $rfqChild = $parent->children()->where('module', 'request_for_quotation')->first();
        if ($rfqChild) {
            $approvals = $rfqChild->approvals()->where('module', 'request_for_quotation')->get();
            if ($approvals->isEmpty() || !$approvals->every(fn ($approval) => $approval->status === 'Approved')) {
                return [
                    'title' => 'RFQ Approval Required',
                    'message' => 'The <strong class="text-danger-600 dark:text-danger-400 font-semibold">Request for Quotation (RFQ)</strong> must be approved first before proceeding with this Purchase Order.',
                    'url' => route('filament.admin.resources.procurements.view-rfq', $parent->id),
                    'buttonLabel' => 'Go to RFQ'
                ];
            }
        }

        // 4. Check AOQ Approved
        $aoqChild = $parent->children()->where('module', 'abstract_of_quotation')->first();
        if ($aoqChild) {
            $approvals = $aoqChild->approvals()->where('module', 'abstract_of_quotation')->get();
            if ($approvals->isEmpty() || !$approvals->every(fn ($approval) => $approval->status === 'Approved')) {
                return [
                    'title' => 'AOQ Approval Required',
                    'message' => 'The <strong class="text-danger-600 dark:text-danger-400 font-semibold">Abstract of Quotation (AOQ)</strong> must be approved first before proceeding with this Purchase Order.',
                    'url' => route('filament.admin.resources.procurements.view-aoq', $parent->id),
                    'buttonLabel' => 'Go to AOQ'
                ];
            }
        }

        // 5. Check BAC Approved
        $bacChild = $parent->children()->where('module', 'bac_resolution_recommending_award')->first();
        if (!$bacChild || $bacChild->status !== 'Approved') {
            return [
                'title' => 'BAC Resolution Required',
                'message' => 'The <strong class="text-danger-600 dark:text-danger-400 font-semibold">BAC Resolution Recommending Award</strong> must be approved first before proceeding with this Purchase Order.',
                'url' => route('filament.admin.resources.procurements.view-bac', $parent->id),
                'buttonLabel' => 'Go to BAC Resolution'
            ];
        }

        return null; // All requirements met
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
                TextEntry::make('procurement_id')
                    ->label('PO No.'),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pending' => 'warning',
                        'Approved' => 'success',
                        'Locked' => 'danger',
                        'Rejected' => 'danger',
                        default => 'gray',
                    })
                    ->getStateUsing(function ($record) {
                        $approvals = $record->approvals()->where('module', 'purchase_order')->get();
                        if ($approvals->isEmpty()) {
                            return 'Pending';
                        } elseif ($approvals->contains('status', 'Rejected')) {
                            return 'Rejected';
                        } elseif ($approvals->every(fn ($approval) => $approval->status === 'Approved')) {
                            return 'Approved';
                        }
                        return $record->status;
                    }),
                TextEntry::make('created_at')
                    ->label('Date Filed')
                    ->date('Y-m-d'),
                TextEntry::make('title'),
                TextEntry::make('requested_by')
                    ->label('Requested By')
                    ->getStateUsing(function ($record) {
                        $parent = $record->parent;
                        $pr = $parent ? $parent->children()->where('module', 'purchase_request')->first() : null;
                        return $pr && $pr->requester ? $pr->requester->full_name : 'Not set';
                    }),
                TextEntry::make('procurement_type')
                    ->badge()
                    ->color(fn ($state) => $state === 'small_value_procurement' ? 'info' : 'primary')
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state))),
                TextEntry::make('fundCluster.name')
                    ->label('Fund Cluster'),
                TextEntry::make('category.name')
                    ->label('Category'),

                // PO Details Section with Visual Feedback
                TextEntry::make('place_of_delivery')
                    ->label('Place of Delivery')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => $state ?: 'Not set'),

                TextEntry::make('date_of_delivery')
                    ->label('Date of Delivery')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('Y-m-d') : 'Not set'),

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
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('Y-m-d') : 'Not set'),
            ])
            ->columns(4);

        $schema[] = Section::make('Approval Stages')
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
                        TextEntry::make('hdr_action_date')
                            ->label('')
                            ->state('Action Date'),
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
                        TextEntry::make('action_at')
                            ->label('')
                            ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('M d, Y') : '—')
                            ->color(fn ($record) => $record->status === 'Rejected' ? 'danger' 
                                                : ($record->status === 'Approved' ? 'success' : 'gray'))
                            ->icon(fn ($record) => $record->status === 'Approved' ? 'heroicon-o-check-circle'
                                                : ($record->status === 'Rejected' ? 'heroicon-o-x-circle' : '')),
                    ])
                    ->columns(5)
                    ->getStateUsing(function ($record) {
                        $approvals = $record->approvals()
                            ->where('module', 'purchase_order')
                            ->with('employee')
                            ->orderBy('sequence')
                            ->get();
                        return $approvals->isEmpty() ? collect() : $approvals;
                    }),
                TextEntry::make('no_approvers')
                    ->label('')
                    ->default('No approvers assigned.')
                    ->hidden(fn ($record) => $record->approvals()->where('module', 'purchase_order')->count() > 0),
            ]);

        return $infolist->schema($schema);
    }

    protected function arePoDetailsComplete(): bool
    {
        return !empty($this->record->place_of_delivery)
            && !empty($this->record->date_of_delivery)
            && !empty($this->record->payment_term)
            && !empty($this->record->ors_burs_no)
            && !empty($this->record->ors_burs_date);
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $hasBacApproved = $this->hasBacApproved();
        $isLocked = $this->record->status === 'Locked';
        $isRejected = $this->isRejected();
        
        // Check if user can edit
        $canEdit = $user && $user->can('update', $this->record);

        $actions = [];

        // REVISE BUTTON: Only show when status is "Rejected"
        if ($isRejected && $hasBacApproved) {
            $actions[] = Actions\Action::make('revisePo')
                ->label('Revise PO')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->disabled(fn () => !$canEdit)
                ->tooltip(fn () => !$canEdit ? 'You do not have permission to revise' : null)
                ->requiresConfirmation()
                ->modalHeading('Revise Purchase Order')
                ->modalDescription('This will reset the PO status to Pending. You can then update the details before locking again.')
                ->modalSubmitActionLabel('Revise PO')
                ->action(function () {
                    // Reset PO status to Pending
                    $this->record->update(['status' => 'Pending']);

                    // Reset ALL approvals to Pending and clear dates/remarks
                    $this->record->approvals()
                        ->where('module', 'purchase_order')
                        ->update([
                            'status' => 'Pending',
                            'action_at' => null,
                            'remarks' => null,
                        ]);

                    if ($this->record->parent_id) {
                        $parent = Procurement::find($this->record->parent_id);
                        if ($parent) {
                            \App\Helpers\ProcurementStatusHelper::updateParentStatus($parent);
                        }
                    }

                    // Log the revision
                    ActivityLogger::log(
                        'Revised Purchase Order',
                        'PO ' . $this->record->procurement_id . ' was revised by ' . auth()->user()->name
                    );

                    Notification::make()
                        ->title('PO has been revised successfully.')
                        ->body('You can now update the details before locking again.')
                        ->success()
                        ->send();

                    $this->record->refresh();
                });
        }

        // View PDF action (visible to everyone, but disabled if no BAC approved)
        $viewPdf = Actions\Action::make('viewPdf')
            ->label('View PDF')
            ->icon('heroicon-o-document-text')
            ->url(fn () => route('procurements.po.pdf', $this->record->id), true)
            ->color('info')
            ->disabled(fn () => !$hasBacApproved)
            ->tooltip(fn () => !$hasBacApproved ? 'BAC Resolution must be approved first' : null);

        // BAC Warning Modal - Show as a mounted action if no BAC approved
        if (!$hasBacApproved) {
            $bacWarning = Actions\Action::make('bacWarning')
                ->label('')
                ->modalHeading('BAC Resolution Required')
                ->modalDescription('The BAC Resolution Recommending Award must be approved first before you can manage this Purchase Order.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Go to BAC Resolution')
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalIconColor('danger')
                ->color('danger')
                ->extraModalFooterActions([
                    Actions\Action::make('goToBac')
                        ->label('Go to BAC Resolution')
                        ->url(route('filament.admin.resources.procurements.view-bac', $this->record->parent_id))
                        ->color('primary')
                        ->button(),
                ])
                ->modalCancelAction(false)
                ->closeModalByClickingAway(false)
                ->extraAttributes(['style' => 'display: none;']);

            return [$bacWarning, ...$actions, $viewPdf];
        }

        // Set PO Details Modal — HIDDEN when locked OR fully approved
        $setPoDetails = Actions\Action::make('setPoDetails')
            ->label('Set PO Details')
            ->icon('heroicon-o-pencil-square')
            ->color(fn () => $canEdit && !$isLocked && !$this->isFullyApproved() && !$isRejected ? 'primary' : 'gray')
            ->disabled(fn () => !$canEdit || $isLocked || $this->isFullyApproved() || $isRejected)
            ->tooltip(fn () => 
                !$canEdit ? 'You do not have permission to edit' : 
                ($isLocked ? 'PO is locked' : 
                ($this->isFullyApproved() ? 'PO is fully approved' : 
                ($isRejected ? 'PO is rejected' : null)))
            )
            ->visible(fn () => !$isLocked && !$this->isFullyApproved() && !$isRejected)
            ->fillForm(fn ($record) => $record->only([
                'place_of_delivery',
                'date_of_delivery',
                'payment_term',
                'ors_burs_no',
                'ors_burs_date',
            ]))
            ->form([
                Forms\Components\TextInput::make('place_of_delivery')
                    ->label('Place of Delivery')
                    ->default('DICT CAR, Baguio City')
                    ->required(),
                Forms\Components\DatePicker::make('date_of_delivery')
                    ->label('Date of Delivery')
                    ->minDate(now()->addDay())
                    ->required(),
                Forms\Components\Textarea::make('payment_term')
                    ->label('Payment Term')
                    ->default('within 5 working days upon Inspection and Acceptance')
                    ->required(),
                Forms\Components\TextInput::make('ors_burs_no')
                    ->label('ORS/BURS No.'),
                Forms\Components\DatePicker::make('ors_burs_date')
                    ->label('Date of ORS/BURS')
                    ->required(),
            ])
            ->action(function (array $data) {
                $this->record->update($data);
                $this->record->refresh();

                ActivityLogger::log(
                    'Set PO Details',
                    'PO details for ' . $this->record->procurement_id . ' were set by ' . Auth::user()->name
                );

                Notification::make()
                    ->title('PO Details updated successfully')
                    ->success()
                    ->send();
            })
            ->modalSubmitAction(fn ($action) => $action->label('Save Changes')->color('primary'));

        // Lock Action — hide when locked OR fully approved OR rejected
        $lock = Actions\Action::make('lock')
            ->label('Lock')
            ->icon('heroicon-o-lock-closed')
            ->color(fn () => $canEdit && $this->arePoDetailsComplete() && !$isLocked && !$this->isFullyApproved() && !$isRejected ? 'danger' : 'gray')
            ->disabled(fn () => !$canEdit || !$this->arePoDetailsComplete() || $isLocked || $this->isFullyApproved() || $isRejected)
            ->tooltip(fn () => 
                !$canEdit ? 'You do not have permission to lock' :
                (!$this->arePoDetailsComplete() ? 'Please complete all PO details first' :
                ($isLocked ? 'Already locked' :
                ($this->isFullyApproved() ? 'PO is fully approved' :
                ($isRejected ? 'PO is rejected' : null))))
            )
            ->visible(fn () => !$isLocked && !$this->isFullyApproved() && !$isRejected && $this->arePoDetailsComplete())
            ->requiresConfirmation()
            ->modalHeading('Lock Purchase Order')
            ->modalDescription('Once locked, this PO cannot be edited anymore.')
            ->modalSubmitActionLabel('Yes, Lock PO')
            ->action(function () {
                $this->record->update(['status' => 'Locked']);
                $this->record->refresh();

                // Log the action
                ActivityLogger::log(
                    'Locked Purchase Order',
            'PO ' . $this->record->procurement_id . ' was locked by ' . Auth::user()->name
                );

        // Send Gmail notifications to all PO approvers + requester
        $approvers = $this->record->approvals()
            ->where('module', 'purchase_order')
            ->with('employee.user')
            ->get();

        foreach ($approvers as $approval) {
    $user = $approval->employee->user ?? null;

    // Only send if email exists and is not empty
    if ($user && !empty(trim($user->email))) {
        try {
            \Mail::to($user->email)->send(
                new \App\Mail\PurchaseOrderLockedMail($this->record)
            );
        } catch (\Exception $e) {
            // Fail silently
        }
    }
}

$creator = $this->record->parent?->requester?->user ?? null;

// Only send if creator email exists
if ($creator && !empty(trim($creator->email))) {
    try {
        \Mail::to($creator->email)->send(
            new \App\Mail\PurchaseOrderLockedMail($this->record)
        );
    } catch (\Exception $e) {}
}


        Notification::make()
            ->title('PO locked and approvers notified.')
            ->success()
            ->send();
    })
    ->modalSubmitAction(fn ($action) => $action->label('Lock PO')->color('danger'));


        $actions[] = $setPoDetails;
        $actions[] = $lock;
        $actions[] = $viewPdf;

        return $actions;
    }

    // Inject the warning modal into the page
    public function getFooter(): ?\Illuminate\Contracts\View\View
    {
        $missing = $this->getFirstMissingRequirement();
        if ($missing) {
            return view('filament.widgets.approval-warning-modal', $missing);
        }
        return null;
    }

    // Helper: are all approvers approved?
    protected function isFullyApproved(): bool
    {
        $approvals = $this->record->approvals()
            ->where('module', 'purchase_order')
            ->get();

        return $approvals->isNotEmpty()
            && $approvals->every(fn ($a) => $a->status === 'Approved');
    }

    // Helper: is PO rejected?
    protected function isRejected(): bool
    {
        $approvals = $this->record->approvals()
            ->where('module', 'purchase_order')
            ->get();

        return $approvals->contains('status', 'Rejected');
    }
}