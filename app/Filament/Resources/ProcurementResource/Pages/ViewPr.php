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
use App\Models\ProcurementItem;
use App\Helpers\ActivityLogger; 
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Mail\PurchaseRequestRequesterSetMail;

class ViewPr extends ViewRecord
{
    protected static string $resource = ProcurementResource::class;

    public function mount($record): void
    {
        $child = Procurement::where('parent_id', $record)
                            ->where('module', 'purchase_request')
                            ->firstOrFail();
        $this->record = $child;
        $this->record->refresh();
    }

    public function getTitle(): string
    {
        return "PR No. " . ($this->record->procurement_id ?? 'N/A');
    }

    // Check if PPMP is uploaded (checks PPMP child record's documents)
    protected function hasPpmpDocument(): bool
    {
        if (!$this->record->parent_id) {
            return false;
        }

        $parent = Procurement::find($this->record->parent_id);
        
        if (!$parent) {
            return false;
        }

        // The PPMP document is stored on the PPMP child record, not the parent
        $ppmpChild = $parent->children()->where('module', 'ppmp')->first();
        
        if ($ppmpChild) {
            return $ppmpChild->documents()->where('module', 'ppmp')->exists();
        }

        return false;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        // Get rejection details if rejected
        $rejectionApproval = null;
        if ($this->record->status === 'Rejected') {
            $rejectionApproval = $this->record->approvals()
                ->where('module', 'purchase_request')
                ->where('status', 'Rejected')
                ->with('employee')
                ->orderBy('action_at', 'desc')
                ->first();
        }

        $schema = [];

        // Add rejection notice section if rejected
        if ($rejectionApproval) {
            $schema[] = Section::make('PR Rejected')
                ->schema([
                    TextEntry::make('rejection_remarks')
                        ->label('Rejection Remarks')
                        ->state($rejectionApproval->remarks ?? 'No remarks provided')
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->extraAttributes(['class' => 'bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500']);
        }

        // Add existing sections
        $schema[] = Section::make('Purchase Request Details')
            ->schema([
                TextEntry::make('procurement_id')
                    ->label('PR No.'),
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
                TextEntry::make('requester.full_name')
                    ->label('Requested By')
                    ->default('Not set'),
                TextEntry::make('procurement_type')
                    ->badge()
                    ->color(fn (string $state) => $state === 'small_value_procurement' ? 'info' : 'primary')
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state))),
                TextEntry::make('fundCluster.name')
                    ->label('Fund Cluster'),
                TextEntry::make('category.name')
                    ->label('Category'),
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
                    ->getStateUsing(function ($record) {
                        $approvals = $record->approvals()
                            ->where('module', 'purchase_request')
                            ->with('employee')
                            ->orderBy('sequence')
                            ->get();
                        return $approvals->isEmpty() ? collect() : $approvals;
                    }),
                TextEntry::make('no_approvers')
                    ->label('')
                    ->default('No approvers assigned.')
                    ->hidden(fn ($record) => $record->approvals()->where('module', 'purchase_request')->count() > 0),
            ]);

        $schema[] = Section::make(fn ($record) => $record->basis === 'lot' ? 'Lot Details' : 'Item Details')
            ->schema([
                RepeatableEntry::make('items')
                    ->label('')
                    ->schema([
                        TextEntry::make('sort')->label(fn ($record) => $record->procurement->basis === 'lot' ? 'Lot No.' : 'Item No.'),
                        TextEntry::make('unit')->label('Unit'),
                        TextEntry::make('item_description')->label(fn ($record) => $record->procurement->basis === 'lot' ? 'Lot Description' : 'Item Description'),
                        TextEntry::make('quantity')->label('Qty'),
                        TextEntry::make('unit_cost')->label('Unit Cost')->money('PHP'),
                        TextEntry::make('total_cost')->label('Total Cost')->money('PHP'),
                    ])
                    ->columns(6)
                    ->columnSpanFull(),
                TextEntry::make('grand_total')
                    ->label('Grand Total')
                    ->money('PHP')
                    ->extraAttributes(['class' => 'font-bold text-lg text-right mt-2']),
            ])
            ->collapsible()
            ->columnSpanFull();

        return $infolist->schema($schema);
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $hasPpmp = $this->hasPpmpDocument();
        $isRejected = $this->record->status === 'Rejected';
        $isLockedOrApproved = in_array($this->record->status, ['Locked', 'Approved']);
        $isPending = $this->record->status === 'Pending';

        // Check if user can edit
        $canEdit = $user && $user->can('update', $this->record);

        $actions = [];

        // REVISE BUTTON: Only show when status is "Rejected"
        if ($isRejected && $hasPpmp) {
            $actions[] = Actions\Action::make('revisePr')
                ->label('Revise PR')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->disabled(fn () => !$canEdit)
                ->tooltip(fn () => !$canEdit ? 'You do not have permission to revise' : null)
                ->requiresConfirmation()
                ->modalHeading('Revise Purchase Request')
                ->modalDescription('This will reset the PR status to Pending. You can then update the items before locking again.')
                ->modalSubmitActionLabel('Revise PR')
                ->action(function () {
                    // Reset PR status to Pending
                    $this->record->update(['status' => 'Pending']);

                    // Reset ALL approvals to Pending and clear dates/remarks
                    $this->record->approvals()
                        ->where('module', 'purchase_request')
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
                        'Revised Purchase Request',
                        'PR ' . $this->record->procurement_id . ' was revised by ' . auth()->user()->name
                    );

                    Notification::make()
                        ->title('PR has been revised successfully.')
                        ->body('You can now update the items before locking again.')
                        ->success()
                        ->send();

                    $this->record->refresh();
                });
        }

        // PDF action (visible to everyone, but disabled if no PPMP)
        $viewPdf = Actions\Action::make('viewPdf')
            ->label('View PDF')
            ->icon('heroicon-o-document-text')
            ->url(fn () => route('procurements.pr.pdf', $this->record), true)
            ->color('info')
            ->disabled(fn () =>
                !$hasPpmp ||
                !in_array($this->record->status, ['Locked', 'Approved', 'Rejected'])
            )
            ->tooltip(function () use ($hasPpmp) {
                if (!$hasPpmp) return 'You must upload a PPMP first';
                if ($this->record->status !== 'Locked') return 'PR must be locked before generating PDF';
                return null;
            });

        // MANAGEMENT BUTTONS: Show only when Pending AND not Locked/Approved
        if ($isPending && !$isLockedOrApproved && $hasPpmp) {
            $manageItems = Actions\Action::make('manageItems')
                ->label('Manage Items')
                ->button()
                ->color(fn () => $canEdit ? 'success' : 'gray')
                ->icon('heroicon-o-pencil-square')
                ->disabled(fn () => !$canEdit)
                ->tooltip(fn () => !$canEdit ? 'You do not have permission to edit' : null)
                ->modalWidth('7xl')
                ->fillForm(function () {
                    // Fresh fetch from database
                    $this->record->refresh();
                    
                    $items = $this->record->items()->orderBy('sort')->get()->map(fn ($item) => [
                        'unit' => $item->unit,
                        'item_description' => $item->item_description,
                        'quantity' => $item->quantity,
                        'unit_cost' => $item->unit_cost,
                        'total_cost' => $item->total_cost,
                    ])->toArray();

                    return [
                        'basis' => $this->record->basis ?? 'item',
                        'items' => $items,
                    ];
                })
                ->form([
                    Forms\Components\Select::make('basis')
                        ->label('Basis')
                        ->options([
                            'item' => 'Per Item',
                            'lot' => 'Per Lot',
                        ])
                        ->default('item')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\Repeater::make('items')
                        ->label(fn ($get) => $get('basis') === 'lot' ? 'Lot Details' : 'Item Details')
                        ->schema([
                            Forms\Components\Grid::make(24)->schema([
                                Forms\Components\Select::make('common_item_id')
                                    ->label('Select Pre-saved Item')
                                    ->options(function () {
                                        // Detect correct category
                                        $categoryId = $this->record->category_id 
                                            ?? ($this->record->parent->category_id ?? null);

                                        if (!$categoryId) {
                                            return []; // No category, no items
                                        }

                                        return \App\Models\CommonItem::where('category_id', $categoryId)
                                            ->pluck('item_description', 'id');
                                    })
                                    ->searchable()
                                    ->reactive()
                                    ->columnSpan(24)
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if (!$state) return;

                                        $preset = \App\Models\CommonItem::find($state);
                                        if (!$preset) return;

                                        // Auto-fill fields
                                        $set('unit', $preset->unit);
                                        $set('item_description', $preset->item_description);
                                        $set('unit_cost', $preset->unit_cost);
                                    }),
                                Forms\Components\Placeholder::make('item_number_display')
                                    ->label(fn ($get) => $get('../../basis') === 'lot' ? 'Lot No.' : 'Item No.')
                                    ->content(function (Forms\Get $get, Forms\Components\Component $component) {
                                        // Get the repeater item's key/UUID
                                        $itemKey = $component->getContainer()->getStatePath();
                                    
                                        // Get all items from the repeater
                                        $allItems = $get('../../items');
                                        
                                        if (!is_array($allItems)) {
                                            return '1';
                                        }
                                        
                                        // Find the position of current item by matching the key
                                        $position = 0;
                                        $index = 0;
                                        foreach ($allItems as $key => $item) {
                                            $index++;
                                            if (str_contains($itemKey, (string)$key)) {
                                                $position = $index;
                                                break;
                                            }
                                        }
                                        
                                        return $position > 0 ? (string)$position : (string)count($allItems);
                                    })
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('unit')
                                    ->required()
                                    ->columnSpan(3)
                                    ->extraInputAttributes(['class' => 'min-w-0']),
                                Forms\Components\Textarea::make('item_description')
                                    ->label(fn ($get) => $get('../../basis') === 'lot' ? 'Lot Description' : 'Item Description')
                                    ->required()
                                    ->rows(1)
                                    ->autosize()
                                    ->columnSpan(8)
                                    ->extraInputAttributes(['class' => 'min-w-0']),
                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $qty = (int)($state ?? 0);

                                        if ($qty <= 0) {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Invalid Quantity')
                                                ->body('Quantity must be at least 1.')
                                                ->warning()
                                                ->send();
                                        }

                                        $unitCost = (float)($get('unit_cost') ?? 0);
                                        $cleanQty = $qty > 0 ? $qty : 0;
                                        $set('total_cost', $cleanQty * $unitCost);
                                    })
                                    ->columnSpan(2)
                                    ->extraInputAttributes(['class' => 'min-w-0']),
                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('Unit Cost')
                                    ->numeric()
                                    ->prefix('₱')
                                    ->required()
                                    ->rules(['gt:0'])
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get, $component) {
                                        $value = is_numeric($state) ? (float) $state : 0;

                                        if ($value <= 0) {
                                            $key = 'negative_unit_cost_warning_' . md5($component->getStatePath());
                                            $livewire = $component->getLivewire();

                                            // Show notification ONLY ONCE per field
                                            if (! property_exists($livewire, $key)) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Invalid Unit Cost')
                                                    ->body('You cannot enter a negative number or zero. Please enter a positive amount.')
                                                    ->danger()
                                                    ->icon('heroicon-o-exclamation-triangle')
                                                    ->send();

                                                $livewire->{$key} = true;
                                            }

                                            $set('total_cost', 0);
                                        } else {
                                            $set('total_cost', $value * (float)($get('quantity') ?? 1));
                                        }
                                    })
                                    ->columnSpan(4)
                                    ->extraInputAttributes(['class' => 'min-w-0']),
                                Forms\Components\TextInput::make('total_cost')
                                    ->numeric()
                                    ->prefix('₱')
                                    ->readOnly()
                                    ->columnSpan(5)
                                    ->extraInputAttributes(['class' => 'min-w-0']),
                            ]),
                        ])
                        ->columns(24)
                        ->addable(fn ($get) => !($get('basis') === 'lot' && count($get('items') ?? []) >= 1))
                        ->addActionLabel(fn ($get) => $get('basis') === 'lot' ? 'Add Lot' : 'Add Item')
                        ->reorderable()
                        ->reorderableWithDragAndDrop(true)
                        ->defaultItems(0)
                        ->live(),
                    Forms\Components\Placeholder::make('grand_total')
                        ->label('Grand Total')
                        ->content(function ($get) {
                            $total = collect($get('items'))->sum(fn ($i) => (float) ($i['total_cost'] ?? 0));
                            $formatted = '₱ ' . number_format($total, 2);
                            
                            if ($this->record->procurement_type === 'small_value_procurement' && $total >= 1000000) {
                                return new \Illuminate\Support\HtmlString(
                                    '<span class="text-danger-600 dark:text-danger-400">' . $formatted . ' - Exceeds SVP limit of ₱1,000,000.00</span>'
                                );
                            }
                            
                            return $formatted;
                        })
                        ->extraAttributes(['class' => 'font-bold text-lg text-right mt-2']),
                ])
                ->action(function (array $data) {
                    // Get items and renumber them sequentially
                    $items = collect($data['items'] ?? [])
                        ->filter(function ($item) {
                            // Filter out empty items
                            return !empty($item['item_description']) && 
                                isset($item['quantity']) && $item['quantity'] > 0 && 
                                isset($item['unit_cost']) && $item['unit_cost'] > 0;
                        })
                        ->values() // Reset keys to 0, 1, 2, 3...
                        ->map(function ($item, $index) {
                            $qty = (float) ($item['quantity'] ?? 0);
                            $unitCost = (float) ($item['unit_cost'] ?? 0);
                            
                            return [
                                'sort' => $index + 1, // Auto-number: 1, 2, 3, 4...
                                'unit' => $item['unit'] ?? '',
                                'item_description' => $item['item_description'] ?? '',
                                'quantity' => (int) $qty,
                                'unit_cost' => $unitCost,
                                'total_cost' => $qty * $unitCost,
                            ];
                        })
                        ->all();

                    $grandTotal = collect($items)->sum('total_cost');
                    
                    if ($this->record->procurement_type === 'small_value_procurement' && $grandTotal >= 1000000) {
                        Notification::make()
                            ->title('Grand total must be less than ₱1,000,000.00 for Small Value Procurement.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $this->record->items()->delete();
                    
                    if (!empty($items)) {
                        $this->record->items()->createMany($items);

                        // AUTO-SAVE common items for future pre-saved dropdown
                        $parentCategoryId = $this->record->parent->category_id ?? null;

                        if ($parentCategoryId) {
                            foreach ($items as $item) {
                                $description = trim($item['item_description']);
                                $unit = trim($item['unit']);
                                $unitCost = $item['unit_cost'];
                                $categoryId = $this->record->category_id;

                                // Track usage
                                $tracker = \DB::table('common_item_tracker')
                                    ->where('item_description', $description)
                                    ->first();

                                if ($tracker) {
                                    \DB::table('common_item_tracker')
                                        ->where('id', $tracker->id)
                                        ->increment('count');
                                } else {
                                    \DB::table('common_item_tracker')->insert([
                                        'item_description' => $description,
                                        'unit' => $unit,
                                        'unit_cost' => $unitCost,
                                        'count' => 1,
                                    ]);
                                }

                                // If count reaches 3 ➜ promote to CommonItem
                                if ($tracker && $tracker->count + 1 >= 3) {
                                    \App\Models\CommonItem::updateOrCreate(
                                        [
                                            'item_description' => $description,
                                            'category_id' => $categoryId,
                                        ],
                                        [
                                            'unit' => $unit,
                                            'unit_cost' => $unitCost,
                                        ]);
                                }
                            }
                        }
                    }

                    $this->record->update([
                        'basis' => $data['basis'],
                        'grand_total' => $grandTotal,
                    ]);
                    
                    $this->record->refresh();

                    ActivityLogger::log(
                        'Updated Purchase Request Items',
                        'Items for PR ' . $this->record->procurement_id . ' were updated by ' . auth()->user()->name
                    );

                    Notification::make()
                        ->title('Items updated successfully')
                        ->success()
                        ->send();
                })
                // Disable submit button if SVP limit is exceeded
                ->modalSubmitAction(function ($action) {
                    return $action
                        ->label('Save Changes')
                        ->color('primary')
                        ->disabled(function () {
                            // Access form data from the Livewire component's mounted action data
                            $data = $this->mountedActionsData[0] ?? [];
                            $items = $data['items'] ?? [];
                            $total = collect($items)->sum(fn ($i) => (float) ($i['total_cost'] ?? 0));
                            return $this->record->procurement_type === 'small_value_procurement' && $total >= 1000000;
                        });
                });

            $lockPr = Actions\Action::make('lockPr')
                ->label('Lock')
                ->icon('heroicon-o-lock-closed')
                ->color(fn () => $canEdit ? 'danger' : 'gray')
                ->disabled(fn () => !$canEdit)
                ->tooltip(fn () => !$canEdit ? 'You do not have permission to lock' : null)
                ->visible(fn () => $this->record->items()->count() > 0)
                ->form([
                    Forms\Components\Select::make('requested_by')
                        ->label('Requested By')
                        ->options(function () {
                            $involvedEmployeeIds = collect();

                            // Get the parent procurement record
                            if ($this->record->parent_id) {
                                $parent = Procurement::find($this->record->parent_id);
                                
                                if ($parent) {
                                    // 1. Get employees assigned to parent procurement (from pivot table)
                                    $assignedEmployeeIds = $parent->employees()->pluck('employee_id');
                                    $involvedEmployeeIds = $involvedEmployeeIds->merge($assignedEmployeeIds);

                                    // 2. Get the creator of parent procurement
                                    if ($parent->created_by) {
                                        $creatorEmployee = \App\Models\Employee::whereHas('user', function($q) use ($parent) {
                                            $q->where('id', $parent->created_by);
                                        })->first();
                                        
                                        if ($creatorEmployee) {
                                            $involvedEmployeeIds->push($creatorEmployee->id);
                                        }
                                    }
                                }
                            }

                            // Remove duplicates and filter out nulls
                            $involvedEmployeeIds = $involvedEmployeeIds->unique()->filter();

                            // Fetch employee names
                            return \App\Models\Employee::whereIn('id', $involvedEmployeeIds)
                                ->orderBy('firstname')
                                ->orderBy('lastname')
                                ->get()
                                ->mapWithKeys(function ($employee) {
                                    return [$employee->id => $employee->full_name];
                                })
                                ->toArray();
                        })
                        ->searchable()
                        ->required()
                        ->placeholder('Select employee assigned to this procurement'),
                ])
                ->action(function (array $data) {
                    // Check if this is a re-lock after revision
                    $wasRevised = $this->record->approvals()
                        ->where('module', 'purchase_request')
                        ->where('status', 'Rejected')
                        ->exists();

                    $this->record->update([
                        'status' => 'Locked',
                        'requested_by' => $data['requested_by'],
                    ]);

                    // ------------------------------
                    // EMAIL: Notify Selected Requester
                    // ------------------------------
                    $requester = \App\Models\Employee::find($data['requested_by']);

                    if ($requester && $requester->user && !empty($requester->user->email)) {
                        try {
                            \Mail::to($requester->user->email)->send(
                                new \App\Mail\PurchaseRequestRequesterSetMail($this->record)
                            );
                        } catch (\Exception $e) {
                            \Log::error("Failed to send PR requester-set email: {$e->getMessage()}");
                        }
                    }

                    $this->record->refresh();

                    ActivityLogger::log(
                        'Locked Purchase Request',
                        'Purchase Request ' . ($this->record->procurement_id ?? 'N/A') .
                        ' was locked by ' . (auth()->user()->name ?? 'Unknown User')
                    );

                    // Send Gmail notifications to all assigned approvers
                    $approvers = $this->record->approvals()
                        ->where('module', 'purchase_request')
                        ->with('employee.user')
                        ->get();

                    foreach ($approvers as $approval) {
                        $user = $approval->employee->user ?? null;
                        if ($user && $user->email) {
                            try {
                                // Use different email template based on whether it was revised
                                if ($wasRevised) {
                                    \Mail::to($user->email)->send(
                                        new \App\Mail\PurchaseRequestRevisedAndLockedMail($this->record)
                                    );
                                } else {
                                    \Mail::to($user->email)->send(
                                        new \App\Mail\PurchaseRequestLockedMail($this->record)
                                    );
                                }
                            } catch (\Exception $e) {
                                // Handle silently
                            }
                        }
                    }

                    Notification::make()
                        ->title('Purchase Request locked and approvers notified.')
                        ->success()
                        ->send();
                    return redirect()->route('filament.admin.resources.procurements.view-pr', ['record' => $this->record->parent_id]);
                })
                ->modalSubmitAction(fn ($action) => $action->label('Lock PR')->color('danger'));

            $actions[] = $manageItems;
            $actions[] = $lockPr;
        }

        $actions[] = $viewPdf;

        // PPMP Warning Modal - Show as a mounted action if no PPMP
        if (!$hasPpmp) {
            $ppmpWarning = Actions\Action::make('ppmpWarning')
                ->label('')
                ->modalHeading('⚠️ PPMP Required')
                ->modalDescription('You must upload a PPMP document first before you can manage this Purchase Request.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Go to PPMP')
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalIconColor('danger')
                ->color('danger')
                ->extraModalFooterActions([
                    Actions\Action::make('goToPpmp')
                        ->label('Go to PPMP')
                        ->url(route('filament.admin.resources.procurements.view-ppmp', $this->record->parent_id))
                        ->color('primary')
                        ->button(),
                ])
                ->modalCancelAction(false)
                ->closeModalByClickingAway(false)
                ->extraAttributes(['style' => 'display: none;']);

            array_unshift($actions, $ppmpWarning);
        }

        return $actions;
    }

    // Inject the warning modal into the page
    public function getFooter(): ?\Illuminate\Contracts\View\View
    {
        if (!$this->hasPpmpDocument()) {
            return view('filament.widgets.approval-warning-modal', [
                'title' => 'PPMP Required',
                'message' => 'You must upload a <strong class="text-danger-600 dark:text-danger-400 font-semibold">PPMP document</strong> first before you can manage this Purchase Request.',
                'url' => route('filament.admin.resources.procurements.view-ppmp', $this->record->parent_id),
                'buttonLabel' => 'Go to PPMP'
            ]);
        }
        return null;
    }
}