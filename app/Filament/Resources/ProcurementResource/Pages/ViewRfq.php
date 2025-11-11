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
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\FileUpload;
use App\Models\Procurement;
use App\Models\DefaultApprover;
use App\Models\RfqDistribution;
use App\Models\Supplier;
use App\Models\ProcurementItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use App\Mail\RfqMail;
use Carbon\Carbon;
use Filament\Forms\Components\Placeholder;
use Filament\Support\Exceptions\Halt;
use Filament\Actions\Concerns\CanDispatchEvents;
use App\Helpers\ActivityLogger; 

class ViewRfq extends ViewRecord
{

    protected static string $resource = ProcurementResource::class;

    public function mount($record): void
    {
        $child = Procurement::where('parent_id', $record)
                            ->where('module', 'request_for_quotation')
                            ->firstOrFail();
        \Log::info('ViewRfq mount', [
            'child_id' => $child->id,
            'rfqResponses' => $child->rfqResponses->toArray(),
            'rfqDistributions' => $child->rfqDistributions->toArray(),
            'record' => $record,
        ]);
        $this->record = $child;
        $this->record->load('rfqResponses.supplier', 'rfqDistributions.supplier');
    }

    public function getTitle(): string
    {
        return 'RFQ No. ' . ($this->record->procurement_id ?? 'Not set');
    }

    // Check if PR is approved
    protected function hasPrApproved(): bool
    {
        if (!$this->record->parent_id) {
            return false;
        }

        $parent = Procurement::find($this->record->parent_id);
        
        if (!$parent) {
            return false;
        }

        // Check if PR child record exists and is approved
        $prChild = $parent->children()->where('module', 'purchase_request')->first();
        
        if ($prChild) {
            return $prChild->status === 'Approved';
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
                'message' => 'You must upload a <strong class="text-danger-600 dark:text-danger-400 font-semibold">PPMP document</strong> first before proceeding with this Request for Quotation.',
                'url' => route('filament.admin.resources.procurements.view-ppmp', $parent->id),
                'buttonLabel' => 'Go to PPMP'
            ];
        }

        // 2. Check PR Approved
        $prChild = $parent->children()->where('module', 'purchase_request')->first();
        if (!$prChild || $prChild->status !== 'Approved') {
            return [
                'title' => 'PR Approval Required',
                'message' => 'The <strong class="text-danger-600 dark:text-danger-400 font-semibold">Purchase Request must be approved</strong> first before proceeding with this Request for Quotation.',
                'url' => route('filament.admin.resources.procurements.view-pr', $parent->id),
                'buttonLabel' => 'Go to PR'
            ];
        }

        return null; // All requirements met
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
                    ->columns(4)
                    ->collapsible(),
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
            ]);
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $canEdit = $user && $user->can('update', $this->record);
        $isLocked = $this->record->status === 'Locked';
        $hasDelivery = !empty($this->record->delivery_mode) && !empty($this->record->delivery_value);
        $hasDeadline = !empty($this->record->deadline_date);
        $isApproved = $this->record->approvals()
            ->where('module', 'request_for_quotation')
            ->where('status', 'Approved')
            ->exists();
        $hasPrApproved = $this->hasPrApproved();

        $actions = [];

        // Show setDelivery, setDeadline, and lock only before approval AND if PR is approved
        if (!$isApproved && !$isLocked && $hasPrApproved) {
            $actions[] = Actions\Action::make('setDelivery')
                ->label('Set Delivery Period')
                ->icon('heroicon-o-clock')
                ->visible(fn () => $canEdit)
                ->form([
                    Radio::make('delivery_mode')
                        ->label('Delivery Mode')
                        ->options([
                            'days' => 'Number of Days',
                            'date' => 'Specific Date',
                        ])
                        ->required()
                        ->reactive(),
                    TextInput::make('delivery_days')
                        ->label('Delivery Days')
                        ->numeric()
                        ->minValue(1)
                        ->required(fn (callable $get) => $get('delivery_mode') === 'days')
                        ->visible(fn (callable $get) => $get('delivery_mode') === 'days'),
                    DatePicker::make('delivery_date')
                        ->label('Delivery Date')
                        ->minDate(now())
                        ->required(fn (callable $get) => $get('delivery_mode') === 'date')
                        ->visible(fn (callable $get) => $get('delivery_mode') === 'date'),
                ])
                ->fillForm(function () {
                    return [
                        'delivery_mode' => $this->record->delivery_mode,
                        'delivery_days' => $this->record->delivery_mode === 'days' ? $this->record->delivery_value : null,
                        'delivery_date' => $this->record->delivery_mode === 'date' && $this->record->delivery_value ? Carbon::parse($this->record->delivery_value) : null,
                    ];
                })
                ->action(function (array $data) {
                    $value = $data['delivery_mode'] === 'days' ? $data['delivery_days'] : ($data['delivery_date'] ? Carbon::parse($data['delivery_date'])->toDateString() : null);
                    $this->record->update([
                        'delivery_mode' => $data['delivery_mode'],
                        'delivery_value' => $value,
                    ]);
                    $this->record->refresh();
                    Notification::make()->title('Delivery period updated')->success()->send();
                });

            $actions[] = Actions\Action::make('setDeadline')
                ->label('Set Submission Deadline')
                ->icon('heroicon-o-calendar')
                ->visible(fn () => $canEdit) // Restrict visibility to authorized users
                ->form([
                    DateTimePicker::make('deadline_date')
                        ->label('Submission Deadline')
                        ->minDate(now())
                        ->required(),
                ])
                ->fillForm(function () {
                    return [
                        'deadline_date' => $this->record->deadline_date ? Carbon::parse($this->record->deadline_date) : null,
                    ];
                })
                ->action(function (array $data) {
                    $this->record->update([
                        'deadline_date' => Carbon::parse($data['deadline_date'])->toDateTimeString(),
                    ]);
                    $this->record->refresh();
                    Notification::make()->title('Submission deadline updated')->success()->send();
                });

            $actions[] = Actions\Action::make('lock')
                ->label('Lock')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $canEdit && $hasDelivery && $hasDeadline) // Restrict visibility to authorized users and keep existing conditions

                ->action(function () {
                    $this->record->update(['status' => 'Locked']);

                    \App\Helpers\ActivityLogger::log(
    'Locked Request for Quotation',
    'Request for Quotation ' . ($this->record->procurement_id ?? 'N/A') .
    ' was locked by ' . (auth()->user()->name ?? 'Unknown User')
);

                    $this->record->refresh();

                    // 🔔 Send Gmail notification to assigned approver(s)
                    $approvers = $this->record->approvals()
                        ->where('module', 'request_for_quotation')
                        ->with('employee.user')
                        ->get();

                    foreach ($approvers as $approval) {
                        $user = $approval->employee->user ?? null;
                        if ($user && $user->email) {
                            try {
                                \Mail::to($user->email)->send(
                                    new \App\Mail\RfqLockedMail($this->record)
                                );
                            } catch (\Exception $e) {
                                \Log::error("Failed to send RFQ locked email to {$user->email}: {$e->getMessage()}");
                            }
                        }
                    }

                    Notification::make()
                        ->title('RFQ locked and approvers notified.')
                        ->success()
                        ->send();
                });
        }

        // Combined "Distribute RFQ" action (only after approved, delivery, and deadline)
        if ($isApproved && $hasDelivery && $hasDeadline && $hasPrApproved) {
            $actions[] = Actions\Action::make('distributeRfq')
                ->label('Distribute RFQ')
                ->icon('heroicon-o-paper-airplane')
                ->disabled(function () {
                    return Supplier::whereHas('categories', fn ($query) => $query->where('category_id', $this->record->category_id))->count() == 0;
                })
                ->tooltip(fn ($action) => $action->isDisabled() ? 'No suppliers available for this category.' : null)
                ->form([
                    Repeater::make('suppliers')
                        ->label('Select Suppliers and Send Method')
                        ->schema([
                            Checkbox::make('selected')
                                ->label('Select')
                                ->default(false),
                            TextInput::make('business_name')
                                ->label('Business Name')
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('email_address')
                                ->label('Email Address')
                                ->disabled()
                                ->dehydrated(false),
                            Select::make('method')
                                ->label('Send Method')
                                ->options([
                                    'email' => 'Email',
                                    'hand' => 'Hand Delivery',
                                    'both' => 'Email + Hand Delivery',
                                ])
                                ->default('email')
                                ->required(),
                            Hidden::make('supplier_id')
                                ->required(),
                        ])
                        ->columns(4)
                        ->default(function () {
                            $categoryId = $this->record->category_id;
                            $existingDistributions = $this->record->rfqDistributions->pluck('supplier_id')->toArray();
                            return Supplier::whereHas('categories', fn ($query) => $query->where('category_id', $categoryId))
                                ->get()
                                ->map(function ($supplier) use ($existingDistributions) {
                                    return [
                                        'selected' => in_array($supplier->id, $existingDistributions),
                                        'business_name' => $supplier->business_name ?? 'No business name',
                                        'email_address' => $supplier->email_address ?? 'No email',
                                        'supplier_id' => $supplier->id,
                                        'method' => in_array($supplier->id, $existingDistributions) ? ($this->record->rfqDistributions->where('supplier_id', $supplier->id)->first()->sent_method ?? 'email') : 'email',
                                    ];
                                })
                                ->toArray();
                        })
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false),
                    Textarea::make('email_body')
                        ->label('Email Body (for Email/Both)')
                        ->default('Dear Supplier, please fill and return the attached RFQ by {deadline} to car.bac@dict.gov.ph.')
                        ->rows(4)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $suppliers = collect($data['suppliers'] ?? []);
                    $selectedSuppliers = $suppliers->filter(fn ($supplier) => $supplier['selected'] ?? false);

                    if ($selectedSuppliers->count() < 3) {
                        Notification::make()
                            ->title('At least 3 suppliers are required')
                            ->body('You currently selected only ' . $selectedSuppliers->count() . ' supplier(s). Please select at least 3.')
                            ->warning()
                            ->persistent()
                            ->send();

                        $this->halt();
                    } 

                    // ✅ Add this after the RFQ distribution is complete
    \App\Helpers\ActivityLogger::log(
        'Distributed RFQ',
        'Request for Quotation for Purchase Request ' . ($this->record->procurement_id ?? 'N/A') .
        ' was distributed by ' . (auth()->user()->name ?? 'Unknown User')
    );
                    

                    // Ensure temp directory exists
                    $tempDir = storage_path('app/temp');
                    if (!is_dir($tempDir)) {
                        mkdir($tempDir, 0755, true);
                    }

                    // Generate PDF
                    $pdf = Pdf::loadView('procurements.rfq', ['procurement' => $this->record]);
                    $pdfPath = storage_path('app/temp/rfq-' . $this->record->id . '.pdf');
                    $pdf->save($pdfPath);

                    // Prepare email body with STATIC EMAIL
                    $deadline = $this->record->deadline_date ? $this->record->deadline_date->format('F j, Y g:i A') : 'Not specified';
                    $creatorName = $this->record->creator?->name ?? 'DICT CAR Bids and Awards Committee';
                    
                    $emailBody = str_replace(
                        ['{deadline}', '{user_email}', '{creator_name}', '{creator_email}'],
                        [
                            $deadline,
                            'car.bac@dict.gov.ph',
                            $creatorName,
                            'car.bac@dict.gov.ph'
                        ],
                        $data['email_body']
                    );

                    foreach ($selectedSuppliers as $supplierData) {
                        $supplierId = $supplierData['supplier_id'];
                        $method = $supplierData['method'];

                        $rfqDistribution = RfqDistribution::firstOrCreate([
                            'procurement_id' => $this->record->id,
                            'supplier_id' => $supplierId,
                        ]);

                        $supplier = Supplier::find($supplierId);
                        $email = $supplier->email_address;
                        $sentTo = '';

                        if (in_array($method, ['email', 'both'])) {
                            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                try {
                                    Mail::to($email)->send(new RfqMail($emailBody, $pdfPath)); // Pass processed emailBody
                                    $sentTo .= $email . ' ';
                                } catch (\Exception $e) {
                                    $sentTo .= 'Failed to send email: ' . $e->getMessage() . ' ';
                                    \Log::error('Failed to send RFQ email', [
                                        'supplier_id' => $supplierId,
                                        'email' => $email,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            } else {
                                $sentTo .= 'Invalid or missing email ';
                            }
                        }
                        if (in_array($method, ['hand', 'both'])) {
                            $sentTo .= 'Hand delivered ';
                        }

                        $rfqDistribution->update([
                            'sent_at' => now(),
                            'sent_method' => $method,
                            'sent_to' => trim($sentTo) ?: 'No delivery method applied',
                        ]);

                        \Log::info('RFQ distributed to supplier', [
                            'procurement_id' => $this->record->id,
                            'supplier_id' => $supplierId,
                            'business_name' => $supplier->business_name,
                            'email' => $email,
                            'method' => $method,
                            'sent_to' => trim($sentTo),
                        ]);
                    }

                    // Clean up unselected suppliers
                    $selectedSupplierIds = $selectedSuppliers->pluck('supplier_id')->toArray();
                    RfqDistribution::where('procurement_id', $this->record->id)
                        ->whereNotIn('supplier_id', $selectedSupplierIds)
                        ->delete();

                    // Clean up PDF file
                    if (file_exists($pdfPath)) {
                        unlink($pdfPath);
                    }

                    Notification::make()
                        ->title('RFQ distributed to ' . $selectedSuppliers->count() . ' suppliers')
                        ->success()
                        ->send();
                });
        }

        $actions[] = Actions\Action::make('viewPdf')
            ->label('View PDF')
            ->icon('heroicon-o-document-text')
            ->url(fn () => route('procurements.rfq.pdf', $this->record->parent_id), true)
            ->color('info');

        // PR Warning Modal - Show as a mounted action if PR is not approved
        if (!$hasPrApproved) {
            $prWarning = Actions\Action::make('prWarning')
                ->label('')
                ->modalHeading('⚠️ PR Approval Required')
                ->modalDescription('The Purchase Request must be approved first before you can manage this Request for Quotation.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Go to PR')
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalIconColor('danger')
                ->color('danger')
                ->extraModalFooterActions([
                    Actions\Action::make('goToPr')
                        ->label('Go to PR')
                        ->url(route('filament.admin.resources.procurements.view-pr', $this->record->parent_id))
                        ->color('primary')
                        ->button(),
                ])
                ->modalCancelAction(false)
                ->closeModalByClickingAway(false)
                ->extraAttributes(['style' => 'display: none;']);

            array_unshift($actions, $prWarning);
        }

        return $actions;
    }
    
    public function getFooter(): ?\Illuminate\Contracts\View\View
    {
        $missing = $this->getFirstMissingRequirement();
        if ($missing) {
            return view('filament.widgets.approval-warning-modal', $missing);
        }
        return view('procurements.rfq-warning-script');
    }
}