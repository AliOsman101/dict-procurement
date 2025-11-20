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
use App\Models\Procurement;
use App\Helpers\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ViewBac extends ViewRecord
{
    protected static string $resource = ProcurementResource::class;

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

    // Check if the BAC currently Locked?
    protected function isLocked(): bool
    {
        return $this->record->status === 'Locked';
    }

    // Check if **all** approvers in the BAC module approved?

    protected function isFullyApproved(): bool
    {
        $approvals = $this->record->approvals()
            ->where('module', 'bac_resolution_recommending_award')
            ->get();

        return $approvals->isNotEmpty()
            && $approvals->every(fn ($a) => $a->status === 'Approved');
    }

    // Check if AOQ is approved
    protected function hasAoqApproved(): bool
    {
        if (! $this->record->parent_id) {
            return false;
        }

        $parent = Procurement::find($this->record->parent_id);
        if (! $parent) {
            return false;
        }

        $aoqChild = $parent->children()
            ->where('module', 'abstract_of_quotation')
            ->first();

        if (! $aoqChild) {
            return false;
        }

        $approvals = $aoqChild->approvals()
            ->where('module', 'abstract_of_quotation')
            ->get();

        return $approvals->isNotEmpty()
            && $approvals->every(fn ($approval) => $approval->status === 'Approved');
    }


    protected function getFirstMissingRequirement(): ?array
    {
        if (! $this->record->parent_id) {
            return null;
        }

        $parent = Procurement::find($this->record->parent_id);
        if (! $parent) {
            return null;
        }

        // 1. Check PPMP
        $ppmpChild = $parent->children()->where('module', 'ppmp')->first();
        if (! $ppmpChild || ! $ppmpChild->documents()->where('module', 'ppmp')->exists()) {
            return [
                'title'       => 'PPMP Required',
                'message'     => 'You must upload a <strong class="text-danger-600 dark:text-danger-400 font-semibold">PPMP document</strong> first before proceeding with this BAC Resolution.',
                'url'         => route('filament.admin.resources.procurements.view-ppmp', $parent->id),
                'buttonLabel' => 'Go to PPMP',
            ];
        }

        // 2. Check PR Approved
        $prChild = $parent->children()->where('module', 'purchase_request')->first();
        if (! $prChild || $prChild->status !== 'Approved') {
            return [
                'title'       => 'PR Approval Required',
                'message'     => 'The <strong class="text-danger-600 dark:text-danger-400 font-semibold">Purchase Request must be approved</strong> first before proceeding with this BAC Resolution.',
                'url'         => route('filament.admin.resources.procurements.view-pr', $parent->id),
                'buttonLabel' => 'Go to PR',
            ];
        }

        // 3. Check RFQ Approved
        $rfqChild = $parent->children()->where('module', 'request_for_quotation')->first();
        if ($rfqChild) {
            $approvals = $rfqChild->approvals()
                ->where('module', 'request_for_quotation')
                ->get();

            if ($approvals->isEmpty() || ! $approvals->every(fn ($a) => $a->status === 'Approved')) {
                return [
                    'title'       => 'RFQ Approval Required',
                    'message'     => 'The <strong class="text-danger-600 dark:text-danger-400 font-semibold">Request for Quotation (RFQ)</strong> must be approved first before proceeding with this BAC Resolution.',
                    'url'         => route('filament.admin.resources.procurements.view-rfq', $parent->id),
                    'buttonLabel' => 'Go to RFQ',
                ];
            }
        }

        // 4. Check AOQ Approved
        $aoqChild = $parent->children()->where('module', 'abstract_of_quotation')->first();
        if (! $aoqChild) {
            return [
                'title'       => 'AOQ Not Found',
                'message'     => 'The <strong class="text-danger-600 dark:text-danger-400 font-semibold">Abstract of Quotation</strong> has not been created yet.',
                'url'         => route('filament.admin.resources.procurements.view', $parent->id),
                'buttonLabel' => 'Go to Procurement',
            ];
        }

        $approvals = $aoqChild->approvals()
            ->where('module', 'abstract_of_quotation')
            ->get();

        if ($approvals->isEmpty() || ! $approvals->every(fn ($a) => $a->status === 'Approved')) {
            return [
                'title'       => 'AOQ Approval Required',
                'message'     => 'The <strong class="text-danger-600 dark:text-danger-400 font-semibold">Abstract of Quotation (AOQ)</strong> must be approved first before proceeding with this BAC Resolution.',
                'url'         => route('filament.admin.resources.procurements.view-aoq', $parent->id),
                'buttonLabel' => 'Go to AOQ',
            ];
        }

        return null; // All requirements met
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $rejectionApproval = null;
        if ($this->record->status === 'Rejected') {
            $rejectionApproval = $this->record->approvals()
                ->where('module', 'bac_resolution_recommending_award')
                ->where('status', 'Rejected')
                ->with('employee')
                ->orderBy('action_at', 'desc')
                ->first();
        }

        $schema = [];

        if ($rejectionApproval) {
            $schema[] = Section::make('BAC Resolution Rejected')
                ->schema([
                    TextEntry::make('rejection_remarks')
                        ->label('Rejection Remarks')
                        ->state($rejectionApproval->remarks ?? 'No remarks provided')
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->extraAttributes(['class' => 'bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500']);
        }

        $schema[] = Section::make('BAC Resolution Details')
            ->schema([
                TextEntry::make('procurement_id')->label('BAC Resolution No.'),
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
                    ->getStateUsing(function ($record) {
                        $parent = $record->parent;
                        $pr = $parent?->children()->where('module', 'purchase_request')->first();
                        return $pr && $pr->requester ? $pr->requester->full_name : 'Not set';
                    }),
                TextEntry::make('procurement_type')
                    ->badge()
                    ->color(fn (string $state) => $state === 'small_value_procurement' ? 'info' : 'primary')
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state))),
                TextEntry::make('fundCluster.name')->label('Fund Cluster'),
                TextEntry::make('category.name')->label('Category'),
            ])
            ->columns(4);

        $schema[] = Section::make('Approval Stages')
            ->schema([
                \Filament\Infolists\Components\Grid::make(5)
                    ->schema([
                        TextEntry::make('hdr_procurement_id')->label('')->state('Procurement ID'),
                        TextEntry::make('hdr_approver')->label('')->state('Approver'),
                        TextEntry::make('hdr_sequence')->label('')->state('Sequence'),
                        TextEntry::make('hdr_status')->label('')->state('Status'),
                        TextEntry::make('hdr_action_date')->label('')->state('Action Date'), // ← Now consistent
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
                            ->formatStateUsing(fn ($state) => sprintf(
                                '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium %s">%s</span>',
                                match ($state) {
                                    'Approved' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                                    'Pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
                                    'Rejected' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
                                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100',
                                },
                                $state
                            ))
                            ->html(),
                        TextEntry::make('action_at')
                            ->label('')
                            ->getStateUsing(fn ($record) => $record->action_at ? $record->action_at->format('M d, Y') : '—')
                            ->color(fn ($record) => $record->status === 'Rejected' ? 'danger' : ($record->status === 'Approved' ? 'success' : 'gray'))
                            ->icon(fn ($record) => $record->status === 'Approved' ? 'heroicon-o-check-circle'
                                : ($record->status === 'Rejected' ? 'heroicon-o-x-circle' : '')),
                    ])
                    ->columns(5)
                    ->getStateUsing(function ($record) {
                        return $record->approvals()
                            ->where('module', 'bac_resolution_recommending_award')
                            ->with('employee')
                            ->orderBy('sequence')
                            ->get();
                    }),
            ]);

        return $infolist->schema($schema);
    }

    protected function getHeaderActions(): array
    {
        $hasAoqApproved = $this->hasAoqApproved();

        // Always show View PDF action
        $viewPdf = Actions\Action::make('viewPdf')
            ->label('View PDF')
            ->icon('heroicon-o-document-text')
            ->url(fn () => route('procurements.bac.pdf', $this->record), true)
            ->color('info')
            ->disabled(fn () => !$hasAoqApproved)
            ->tooltip(fn () => !$hasAoqApproved ? 'AOQ must be approved first' : null);

        // If AOQ is not approved, only show the PDF action (disabled) and the warning modal action
        if (!$hasAoqApproved) {
            $aoqWarning = Actions\Action::make('aoqWarning')
                ->label('')
                ->modalHeading('AOQ Approval Required')
                ->modalDescription('The Abstract of Quotation (AOQ) must be approved first before you can manage this BAC Resolution.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Go to AOQ')
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalIconColor('danger')
                ->color('danger')
                ->extraModalFooterActions([
                    Actions\Action::make('goToAoq')
                        ->label('Go to AOQ')
                        ->url(route('filament.admin.resources.procurements.view-aoq', $this->record->parent_id))
                        ->color('primary')
                        ->button(),
                ])
                ->modalCancelAction(false)
                ->closeModalByClickingAway(false)
                ->extraAttributes(['style' => 'display: none;']);

            return [$aoqWarning, $viewPdf];
        }

        // Original actions if AOQ is approved

        $lockAction = Actions\Action::make('lock')
            ->label('Lock')
            ->icon('heroicon-o-lock-closed')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Lock BAC Resolution')
            ->modalDescription('Once locked, this BAC Resolution cannot be edited. Are you sure?')
            ->action(function () {
                $this->record->update(['status' => 'Locked']);
                $this->record->refresh();
 
                //  Log to Activity / History
                ActivityLogger::log(
                    'Locked BAC Resolution',
                    'BAC Resolution ' . $this->record->procurement_id . ' was locked by ' . Auth::user()->name
                );

                // Send Gmail notification to all approvers
                $approvers = $this->record->approvals()
                    ->where('module', 'bac_resolution_recommending_award')
                    ->with('employee.user')
                    ->get();

                foreach ($approvers as $approval) {
                    $user = $approval->employee->user ?? null;
                    if ($user && $user->email) {
                        try {
                            \Mail::to($user->email)->send(
                                new \App\Mail\BacResolutionLockedMail($this->record)
                            );
                        } catch (\Exception $e) {
                            \Log::error("Failed to send BAC Resolution locked email to {$user->email}: {$e->getMessage()}");
                        }
                    }
                }

                Notification::make()
                    ->title('BAC Resolution locked and approvers notified.')
                    ->success()
                    ->send();
            })
            ->visible(fn () => ! $this->isLocked() && ! $this->isFullyApproved());

        return [$lockAction, $viewPdf];
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
}