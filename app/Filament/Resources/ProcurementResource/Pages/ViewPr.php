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
        return $infolist
            ->schema([
                Section::make('Purchase Request Details')
                    ->schema([
                        TextEntry::make('procurement_id')
                            ->label('PR No.'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Pending' => 'warning',
                                'Approved' => 'success',
                                'Locked' => 'danger',
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
                                TextEntry::make('hdr_sequence')
                                    ->label('')
                                    ->state('Sequence'),
                                TextEntry::make('hdr_status')
                                    ->label('')
                                    ->state('Status'),
                                TextEntry::make('hdr_date_approved')
                                    ->label('')
                                    ->state('Date Approved'),
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
                                TextEntry::make('date_approved')
                                    ->label('')
                                    ->state(function ($record) {
                                        return $record->date_approved ?? 'N/A';
                                    })
                                    ->formatStateUsing(function ($state) {
                                        return $state !== 'N/A' ? \Carbon\Carbon::parse($state)->format('Y-m-d') : 'N/A';
                                    }),
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
                    ]),
                Section::make(fn ($record) => $record->basis === 'lot' ? 'Lot Details' : 'Item Details')
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
                    ->columnSpanFull(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $hasPpmp = $this->hasPpmpDocument();
        $isLockedOrApproved = in_array($this->record->status, ['Locked', 'Approved']);

        // PDF action (visible to everyone, but disabled if no PPMP)
        $viewPdf = Actions\Action::make('viewPdf')
            ->label('View PDF')
            ->icon('heroicon-o-document-text')
            ->url(fn () => route('procurements.pr.pdf', $this->record), true)
            ->color('info')
            ->disabled(fn () => !$hasPpmp)
            ->tooltip(fn () => !$hasPpmp ? 'You must upload a PPMP first' : null);

        if ($isLockedOrApproved) {
            return [$viewPdf];
        }

        // Check if user can edit
        $canEdit = $user && $user->can('update', $this->record);

        $manageItems = Actions\Action::make('manageItems')
            ->label('Manage Items')
            ->button()
            ->color(fn () => $hasPpmp && $canEdit ? 'success' : 'gray')
            ->icon('heroicon-o-pencil-square')
            ->disabled(fn () => !$hasPpmp || !$canEdit)
            ->tooltip(function () use ($hasPpmp, $canEdit) {
                if (!$hasPpmp) return 'You must upload a PPMP first';
                if (!$canEdit) return 'You do not have permission to edit';
                return null;
            })
            ->modalWidth('7xl')
            ->visible(fn () => !$isLockedOrApproved)
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
                    ->disabled(fn () => $isLockedOrApproved)
                    ->columnSpanFull(),
                Forms\Components\Repeater::make('items')
                    ->label(fn ($get) => $get('basis') === 'lot' ? 'Lot Details' : 'Item Details')
                    ->schema([
                        Forms\Components\Grid::make(24)->schema([
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
                                ->default(1)
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    $set('total_cost', (float) $state * (float) $get('unit_cost'));
                                })
                                ->required()
                                ->columnSpan(2)
                                ->extraInputAttributes(['class' => 'min-w-0']),
                            Forms\Components\TextInput::make('unit_cost')
                                ->numeric()
                                ->prefix('₱')
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    $set('total_cost', (float) $state * (float) $get('quantity'));
                                })
                                ->required()
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
                }

                $this->record->update([
                    'basis' => $data['basis'],
                    'grand_total' => $grandTotal,
                ]);
                
                $this->record->refresh();

                   \App\Helpers\ActivityLogger::log(
    'Locked Purchase Request',
    'Purchase Request ' . ($this->record->procurement_id ?? 'N/A') .
    ' was locked by ' . (auth()->user()->name ?? 'Unknown User')
);

\App\Helpers\ActivityLogger::log(
    'Rejected Purchase Request',
    'Purchase Request ' . $this->record->procurement_id . ' was rejected by ' . Auth::user()->name
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
            ->color(fn () => $hasPpmp && $canEdit ? 'danger' : 'gray')
            ->disabled(fn () => !$hasPpmp || !$canEdit)
            ->tooltip(function () use ($hasPpmp, $canEdit) {
                if (!$hasPpmp) return 'You must upload a PPMP first';
                if (!$canEdit) return 'You do not have permission to lock';
                return null;
            })
            ->visible(fn () => $this->record->items()->count() > 0 && !$isLockedOrApproved)
            ->form([
                Forms\Components\Select::make('requested_by')
                    ->label('Requested By')
                    ->options(function () {
                        return \App\Models\Employee::all()->mapWithKeys(function ($employee) {
                            return [$employee->id => $employee->full_name];
                        })->toArray();
                    })
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data) {
        // 🔒 Lock the PR
                $this->record->update([
                    'status' => 'Locked',
                    'requested_by' => $data['requested_by'],
                ]);
                $this->record->refresh();

        // 📧 Send Gmail notifications to all assigned approvers
                $approvers = $this->record->approvals()
                    ->where('module', 'purchase_request')
                    ->with('employee.user')
                    ->get();

                foreach ($approvers as $approval) {
                    $user = $approval->employee->user ?? null;
                    if ($user && $user->email) {
                        try {
                            \Mail::to($user->email)->send(
                                new \App\Mail\PurchaseRequestLockedMail($this->record)
                            );
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
            ->modalSubmitAction(fn ($action) => $action->label('Lock PR')->color('danger')); // Ensures Lock modal submit is red

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

            // Final order: PPMP Warning → Manage Items → Lock → View PDF
            return [$ppmpWarning, $manageItems, $lockPr, $viewPdf];
        }

        // Final order: Manage Items → Lock → View PDF
        return [$manageItems, $lockPr, $viewPdf];
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