<?php

namespace App\Filament\Resources\ProcurementResource\Pages;

use App\Filament\Resources\ProcurementResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Actions;
use Filament\Notifications\Notification;
use App\Models\Procurement;
use App\Helpers\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\MinutesOfOpeningLockedMail;
use Carbon\Carbon;

class ViewMo extends ViewRecord
{
    protected static string $resource = ProcurementResource::class;

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

    // Check if Minutes is currently Locked
    protected function isLocked(): bool
    {
        return $this->record->status === 'Locked';
    }

    // Check if all approvers have approved this Minutes module
    protected function isFullyApproved(): bool
    {
        return $this->record->approvals()
            ->where('module', 'minutes_of_opening')
            ->get()
            ->every(fn ($a) => $a->status === 'Approved');
    }

    protected function hasAoqApproved(): bool
    {
        $aoq = Procurement::where('parent_id', $this->record->parent_id)
            ->where('module', 'abstract_of_quotation')
            ->first();

        return $aoq?->approvals()
            ->where('module', 'abstract_of_quotation')
            ->get()
            ->every(fn ($a) => $a->status === 'Approved') ?? false;
    }

    protected function getFirstMissingRequirement(): ?array
    {
        $parent = $this->record->parent;

        // 1. Check PPMP
        $ppmpChild = $parent->children()->where('module', 'ppmp')->first();
        if (!$ppmpChild || !$ppmpChild->documents()->where('module', 'ppmp')->exists()) {
            return [
                'title'       => 'PPMP Required',
                'message'     => 'You must upload a <strong class="text-danger-600 dark:text-danger-400 font-semibold">PPMP document</strong> first.',
                'url'         => route('filament.admin.resources.procurements.view-ppmp', $parent->id),
                'buttonLabel' => 'Go to PPMP',
            ];
        }

        // 2. Check PR Approved
        $prChild = $parent->children()->where('module', 'purchase_request')->first();
        if (!$prChild || $prChild->status !== 'Approved') {
            return [
                'title'       => 'PR Approval Required',
                'message'     => 'The <strong class="text-danger-600 dark:text-danger-400 font-semibold">Purchase Request</strong> must be approved first.',
                'url'         => route('filament.admin.resources.procurements.view-pr', $parent->id),
                'buttonLabel' => 'Go to PR',
            ];
        }

        // 3. Check AOQ Approved
        $aoqChild = $parent->children()->where('module', 'abstract_of_quotation')->first();
        if (!$aoqChild) {
            return [
                'title'       => 'AOQ Not Found',
                'message'     => 'The <strong class="text-danger-600 dark:text-danger-400 font-semibold">Abstract of Quotation</strong> has not been created yet.',
                'url'         => route('filament.admin.resources.procurements.view', $parent->id),
                'buttonLabel' => 'Go to Procurement',
            ];
        }

        $approvals = $aoqChild->approvals()->where('module', 'abstract_of_quotation')->get();
        if ($approvals->isEmpty() || !$approvals->every(fn ($a) => $a->status === 'Approved')) {
            return [
                'title'       => 'AOQ Approval Required',
                'message'     => 'The <strong class="text-danger-600 dark:text-danger-400 font-semibold">Abstract of Quotation (AOQ)</strong> must be approved first.',
                'url'         => route('filament.admin.resources.procurements.view-aoq', $parent->id),
                'buttonLabel' => 'Go to AOQ',
            ];
        }

        return null;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $rejectionApproval = null;
        if ($this->record->status === 'Rejected') {
            $rejectionApproval = $this->record->approvals()
                ->where('module', 'minutes_of_opening')
                ->where('status', 'Rejected')
                ->with('employee')
                ->orderBy('action_at', 'desc')
                ->first();
        }

        $schema = [];

        // Show rejection banner if rejected
        if ($rejectionApproval) {
            $schema[] = Section::make('Minutes of Opening Rejected')
                ->schema([
                    TextEntry::make('rejection_remarks')
                        ->label('Rejection Remarks')
                        ->state($rejectionApproval->remarks ?? 'No remarks provided')
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->extraAttributes(['class' => 'bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500']);
        }

        $schema[] = Section::make('Minutes of Opening Details')
            ->schema([
                TextEntry::make('procurement_id')->label('Minutes No.'),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pending'   => 'warning',
                        'Approved'  => 'success',
                        'Locked'    => 'danger',
                        'Rejected'  => 'danger',
                        default     => 'gray',
                    }),
                TextEntry::make('created_at')->label('Date Filed')->date('F j, Y'),
                TextEntry::make('title')->label('Project Title'),
                TextEntry::make('requested_by')
                    ->label('Requested By')
                    ->getStateUsing(fn () => $this->record->parent?->children()
                        ->where('module', 'purchase_request')
                        ->first()?->requester?->full_name ?? 'N/A'),
                TextEntry::make('fundCluster.name')->label('Fund Cluster'),
                TextEntry::make('category.name')->label('Category'),
                TextEntry::make('bid_opening_datetime')
                    ->label('Date & Time of Opening')
                    ->formatStateUsing(fn ($state) => $state?->format('F j, Y - h:i A') ?? 'Not set'),
            ])
            ->columns(4);

        $schema[] = Section::make('Approval Stages')
            ->schema([
                Grid::make(5)
                    ->schema([
                        TextEntry::make('hdr_procurement_id')->label('')->state('Procurement ID')->weight('bold'),
                        TextEntry::make('hdr_approver')->label('')->state('Approver')->weight('bold'),
                        TextEntry::make('hdr_sequence')->label('')->state('Sequence')->weight('bold'),
                        TextEntry::make('hdr_status')->label('')->state('Status')->weight('bold'),
                        TextEntry::make('hdr_action_date')->label('')->state('Action Date')->weight('bold'),
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
            ]);

        return $infolist->schema($schema);
    }

    protected function getHeaderActions(): array
    {
        $hasAoqApproved = $this->hasAoqApproved();

        // Always show View PDF button
        $viewPdf = Actions\Action::make('viewPdf')
    ->label('View PDF')
    ->icon('heroicon-o-document-text')
    ->url(fn () => route('procurements.mo.pdf', $this->record->parent->id), true)
    ->color('info')
    ->disabled(fn () =>
    !$hasAoqApproved ||
    !in_array($this->record->status, ['Pending', 'Locked', 'Approved', 'Rejected'])
)

    ->tooltip(function () use ($hasAoqApproved) {
        if (!$hasAoqApproved) {
            return 'AOQ must be approved first';
        }
        if ($this->record->status !== 'Locked') {
            return 'Minutes of Opening must be locked before generating PDF';
        }
        return null;
    });


        // If AOQ not approved → block everything
        if (!$hasAoqApproved) {
            $warning = Actions\Action::make('aoqWarning')
                ->label('')
                ->modalHeading('AOQ Approval Required')
                ->modalDescription('The Abstract of Quotation (AOQ) must be approved first.')
                ->modalSubmitAction(false)
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalIconColor('danger')
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

            return [$warning, $viewPdf];
        }

        // Lock Action — only if not yet locked and not fully approved
        $lockAction = Actions\Action::make('lock')
            ->label('Lock')
            ->icon('heroicon-o-lock-closed')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Lock Minutes of Opening')
            ->modalDescription('Once locked, this document cannot be edited. Are you sure?')
            ->action(function () {
                $this->record->update(['status' => 'Locked']);
                $this->record->refresh();

                ActivityLogger::log('Locked Minutes of Opening', 'MO ' . $this->record->procurement_id . ' locked by ' . Auth::user()->name);

                // Send email to all approvers
                foreach ($this->record->approvals()->where('module', 'minutes_of_opening')->with('employee.user')->get() as $approval) {
                    if ($approval->employee?->user?->email) {
                        Mail::to($approval->employee->user->email)
                            ->send(new MinutesOfOpeningLockedMail($this->record));
                    }
                }

                Notification::make()
                    ->title('Minutes of Opening locked and approvers notified.')
                    ->success()
                    ->send();
            })
            ->visible(fn () => !$this->isLocked() && !$this->isFullyApproved());

        return [$lockAction, $viewPdf];
    }

    public function getFooter(): ?\Illuminate\Contracts\View\View
    {
        $missing = $this->getFirstMissingRequirement();
        if ($missing) {
            return view('filament.widgets.approval-warning-modal', $missing);
        }
        return null;
    }
}