<?php

namespace App\Filament\Resources\ReviewProcurementResource\Pages;

use App\Filament\Resources\ReviewProcurementResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\Procurement;
use App\Models\DefaultApprover;
use Carbon\Carbon;
use App\Filament\Resources\ProcurementResource\RelationManagers\RfqResponsesRelationManager;
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
        $this->record->load('rfqResponses.supplier'); 
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
                        TextEntry::make('procurement_id')
                            ->label('RFQ No.'),
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
                            ->getStateUsing(function ($record) {
                                $parent = $record->parent;
                                $pr = $parent ? $parent->children()->where('module', 'purchase_request')->first() : null;
                                return $pr && $pr->requester ? $pr->requester->full_name : 'Not set';
                            }),
                        TextEntry::make('procurement_type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state)))
                            ->color(fn ($state) => $state === 'small_value_procurement' ? 'info' : 'primary'),
                        TextEntry::make('fundCluster.name')
                            ->label('Fund Cluster')
                            ->default('Not set'),
                        TextEntry::make('category.name')
                            ->label('Category')
                            ->default('Not set'),
                        TextEntry::make('delivery_period_display')
                            ->label('Delivery Period')
                            ->state(function ($record) {
                                if ($record->delivery_mode === 'days' && $record->delivery_value) {
                                    return "Within {$record->delivery_value} calendar days upon receipt of Purchase Order";
                                }
                                if ($record->delivery_mode === 'date' && $record->delivery_value) {
                                    return Carbon::parse($record->delivery_value)->format('F j, Y');
                                }
                                return 'Not set';
                            }),
                        TextEntry::make('deadline_date')
                            ->label('Submission Deadline')
                            ->formatStateUsing(fn ($state) => $state instanceof \Carbon\Carbon ? $state->format('F j, Y, g:i A') : 'Not set'),
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
                                TextEntry::make('hdr_designation')
                                    ->label('')
                                    ->state('Designation'),
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
                                TextEntry::make('procurement.procurement_id')
                                    ->label('')
                                    ->default('Not set'),
                                TextEntry::make('employee.full_name')
                                    ->label('')
                                    ->default('Not set'),
                                TextEntry::make('designation')
                                    ->label('')
                                    ->formatStateUsing(function ($state, $record) {
                                        if ($record->module === 'request_for_quotation' && $record->procurement->office_section) {
                                            $section = str_replace('DICT CAR - ', '', $record->procurement->office_section);
                                            $abbr = $section === 'Admin and Finance Division' ? 'AFD' : 'TOD';
                                            return $state ? "{$state} ({$abbr})" : 'Not set';
                                        }
                                        return $state ?? 'Not set';
                                    })
                                    ->default('Not set'),
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
                                $approver = DefaultApprover::where('module', 'request_for_quotation')
                                    ->where('office_section', $record->office_section)
                                    ->first();
                                if (!$approver) {
                                    return collect();
                                }
                                $approval = $record->approvals()
                                    ->where('module', 'request_for_quotation')
                                    ->where('employee_id', $approver->employee_id)
                                    ->with('employee')
                                    ->first();
                                return $approval ? collect([$approval]) : collect();
                            }),
                        TextEntry::make('no_approvers')
                            ->label('')
                            ->default('No approvers assigned.')
                            ->hidden(fn ($record) => $record->approvals()->where('module', 'request_for_quotation')->count() > 0),
                    ])
                    ->collapsible(),
                
               Section::make('Supplier Responses')
                    ->schema([
                        RepeatableEntry::make('rfqResponses')
                            ->label('')
                            ->schema([
                                TextEntry::make('supplier.business_name')
                                    ->label('Supplier')
                                    ->default('Not set')
                                    ->columnSpan(1),
                                TextEntry::make('supplier_documents')
                                    ->label('Supplier Documents')
                                    ->html()
                                    ->getStateUsing(function ($record) {
                                        $documents = $record->documents;
                                        if (empty($documents)) {
                                            return 'No documents uploaded';
                                        }
                                        if (is_string($documents)) {
                                            $fileName = basename($documents);
                                            return '<a href="' . Storage::url($documents) . '" target="_blank" class="text-primary-600 hover:underline">' . $fileName . '</a>';
                                        }
                                        if (is_array($documents)) {
                                            return collect($documents)->map(fn ($path) => '<a href="' . Storage::url($path) . '" target="_blank" class="text-primary-600 hover:underline">' . basename($path) . '</a>')->implode('<br>');
                                        }
                                        return 'No documents uploaded';
                                    })
                                    ->columnSpan(2),
                                TextEntry::make('rfq_document')
                                    ->label('RFQ Document')
                                    ->html()
                                    ->getStateUsing(function ($record) {
                                        $rfqDocument = $record->rfq_document;
                                        if (empty($rfqDocument)) {
                                            return 'No RFQ document uploaded';
                                        }
                                        $fullPath = $rfqDocument;
                                        if (!str_starts_with($rfqDocument, 'rfq-original-documents/')) {
                                            $fullPath = 'rfq-original-documents/' . $rfqDocument;
                                        }
                                        $disk = Storage::disk('public');
                                        $exists = $disk->exists($fullPath);
                                        if ($exists) {
                                            $url = $disk->url($fullPath);
                                            $filename = basename($rfqDocument);
                                            return '<a href="' . e($url) . '" target="_blank" class="text-primary-600 hover:underline inline-flex items-center gap-1">' . 
                                                e($filename) . 
                                                ' <span class="text-blue-500">📄</span>' .
                                                '</a>';
                                        }
                                        return '<span class="text-red-600 text-sm">File not found</span>';
                                    })
                                    ->columnSpan(1), 
                                TextEntry::make('view_response_pdf')
                                    ->label('')
                                    ->html()
                                    ->getStateUsing(function ($record) {
                                        $url = route('procurements.rfq-response.pdf', $record->id);
                                        return '<a href="' . e($url) . '" target="_blank" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors">' .
                                            '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>' .
                                            'View Response PDF' .
                                            '</a>';
                                    })
                                    ->columnSpan(2), 
                            ])
                            ->columns(5) 
                            ->getStateUsing(function ($record) {
                                return $record->rfqResponses()->with('supplier')->get();
                            })
                            ->hidden(fn ($record) => $record->rfqResponses()->count() === 0),
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

        // Check if the module is Locked and if previous approvers have approved
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

        if (!$canAct) {
            return [
                Actions\Action::make('viewPdf')
                    ->label('View PDF')
                    ->icon('heroicon-o-document-text')
                    ->url(fn () => route('procurements.rfq.pdf', $this->record->parent_id), true)
                    ->color('info'),
            ];
        }

        return [
            Actions\Action::make('viewPdf')
                ->label('View PDF')
                ->icon('heroicon-o-document-text')
                ->url(fn () => route('procurements.rfq.pdf', $this->record->parent_id), true)
                ->color('info'),
            Actions\Action::make('approve')
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

                        // Check if all approvals are approved
                        $allApproved = $this->record->approvals()->where('module', 'request_for_quotation')
                            ->where('status', 'Pending')
                            ->doesntExist();

                        if ($allApproved) {
                            $this->record->update(['status' => 'Approved']);
                        }

                        // Log action
            \App\Helpers\ActivityLogger::log(
                'Approved Request for Quotation',
                'Request for Quotation ' . ($this->record->procurement_id ?? 'N/A') .
                ' was approved by ' . (auth()->user()->name ?? 'Unknown User')
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
                }),
            Actions\Action::make('reject')
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

                        // Set module and parent status to Rejected
                        $this->record->update(['status' => 'Rejected']);
                        $this->record->parent->update(['status' => 'Rejected']);

                        \App\Helpers\ActivityLogger::log(
                'Rejected Request for Quotation',
                'Request for Quotation ' . ($this->record->procurement_id ?? 'N/A') .
                ' was rejected by ' . (auth()->user()->name ?? 'Unknown User') .
                ' with remarks: "' . ($data['remarks'] ?? 'No remarks') . '"'
            );

                       $procurement = $this->record;
        $approver = auth()->user();
        $employees = $procurement->employees ?? collect();
        $creator = $procurement->creator ?? $procurement->requester ?? $procurement->parent?->requester ?? null;

        foreach ($employees as $employee) {
            if ($employee->email) {
                \Mail::to($employee->email)->send(
                    new \App\Mail\RequestForQuotationStatusMail($procurement, 'Rejected', $approver)
                );
            }
        }

        if ($creator && $creator->email) {
            \Mail::to($creator->email)->send(
                new \App\Mail\RequestForQuotationStatusMail($procurement, 'Rejected', $approver)
            );
        }

        Notification::make()->title('RFQ rejected')->danger()->send();
        $this->record->refresh();
                    }
                }),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            RfqResponsesRelationManager::class,
        ];
    }

    
    protected function configureRelationManager($manager)
    {
        if ($manager instanceof RfqResponsesRelationManager) {
            
            $manager->actions([])
                  ->bulkActions([])
                  ->headerActions([]); 
        }
    }
}