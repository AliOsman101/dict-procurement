<?php

namespace App\Filament\Resources\ProcurementResource\Pages;

use App\Filament\Resources\ProcurementResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Grid;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use App\Models\Procurement;
use App\Models\AoqEvaluation;
use App\Models\RfqResponse;
use App\Models\Supplier;
use App\Models\ProcurementItem;
use App\Models\RfqDistribution;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Helpers\ActivityLogger;
use Illuminate\Support\Facades\Auth;

class ViewAoq extends ViewRecord
{
    protected static string $resource = ProcurementResource::class;
    protected $tiedSuppliers = null;
    protected $tieBreakingMethod = null;
    public $showTieBreakingAnimation = false;


    // Mount the component and load AOQ data
    public function mount($record): void
    {
        try {
            $aoq = Procurement::where('parent_id', $record)
                ->where('module', 'abstract_of_quotation')
                ->firstOrFail();

            $this->record = $aoq;
            $this->loadCustomCollections();

        } catch (ModelNotFoundException $e) {
            Notification::make()
                ->title('Error')
                ->body('Abstract of Quotation not found.')
                ->danger()
                ->send();
            redirect()->to(static::getResource()::getUrl('index'));
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error Loading AOQ')
                ->body($e->getMessage())
                ->danger()
                ->send();
            redirect()->to(static::getResource()::getUrl('index'));
        }
    }

    // Check if RFQ is approved
    protected function hasRfqApproved(): bool
    {
        if (!$this->record->parent_id) {
            return false;
        }

        $parent = Procurement::find($this->record->parent_id);
        
        if (!$parent) {
            return false;
        }

        $rfqChild = $parent->children()->where('module', 'request_for_quotation')->first();
        
        if ($rfqChild) {
            $approvals = $rfqChild->approvals()->where('module', 'request_for_quotation')->get();
            
            if ($approvals->isEmpty()) {
                return false;
            }
            
            return $approvals->every(fn ($approval) => $approval->status === 'Approved');
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
                'message' => 'You must upload a <strong class="text-danger-600 dark:text-danger-400 font-semibold">PPMP document</strong> first before proceeding with this Abstract of Quotation.',
                'url' => route('filament.admin.resources.procurements.view-ppmp', $parent->id),
                'buttonLabel' => 'Go to PPMP'
            ];
        }

        // 2. Check PR Approved
        $prChild = $parent->children()->where('module', 'purchase_request')->first();
        if (!$prChild || $prChild->status !== 'Approved') {
            return [
                'title' => 'PR Approval Required',
                'message' => 'The <strong class="text-danger-600 dark:text-danger-400 font-semibold">Purchase Request must be approved</strong> first before proceeding with this Abstract of Quotation.',
                'url' => route('filament.admin.resources.procurements.view-pr', $parent->id),
                'buttonLabel' => 'Go to PR'
            ];
        }

        // 3. Check RFQ Approved
        $rfqChild = $parent->children()->where('module', 'request_for_quotation')->first();
        if (!$rfqChild) {
            return [
                'title' => 'RFQ Not Found',
                'message' => 'The <strong class="text-danger-600 dark:text-danger-400 font-semibold">Request for Quotation</strong> has not been created yet.',
                'url' => route('filament.admin.resources.procurements.view', $parent->id),
                'buttonLabel' => 'Go to Procurement'
            ];
        }

        $approvals = $rfqChild->approvals()->where('module', 'request_for_quotation')->get();
        if ($approvals->isEmpty() || !$approvals->every(fn ($approval) => $approval->status === 'Approved')) {
            return [
                'title' => 'RFQ Approval Required',
                'message' => 'The <strong class="text-danger-600 dark:text-danger-400 font-semibold">Request for Quotation (RFQ)</strong> must be approved first before proceeding with this Abstract of Quotation.',
                'url' => route('filament.admin.resources.procurements.view-rfq', $parent->id),
                'buttonLabel' => 'Go to RFQ'
            ];
        }

        return null; // All requirements met
    }

    // Load related data for the AOQ record
    protected function loadCustomCollections(): void
    {
        $parent = Procurement::find($this->record->parent_id);
        
        $pr = Procurement::where('parent_id', $this->record->parent_id)
            ->where('module', 'purchase_request')
            ->with([
                'procurementItems' => function ($query) {
                    $query->orderBy('sort');
                },
                'requester:id,firstname,lastname'
            ])
            ->first();

        $rfq = Procurement::where('parent_id', $this->record->parent_id)
            ->where('module', 'request_for_quotation')
            ->first();

        $rfqResponses = collect();
        if ($rfq) {
            $rfqResponses = RfqResponse::where('procurement_id', $rfq->id)
                ->with([
                    'supplier:id,business_name',
                    'quotes.procurementItem:id,procurement_id,item_description,quantity,unit,unit_cost,total_cost,sort',
                    'aoqEvaluations' => function ($query) {
                        $query->where('procurement_id', $this->record->id);
                    }
                ])
                ->get();
        }

        $this->record->rfqResponses = $rfqResponses;
        $this->record->procurementItems = $pr ? $pr->procurementItems : collect();
        $this->record->requester = $pr ? $pr->requester : null;
        $this->record->setRelation('parent', $parent);

        $this->record->load([
            'approvals' => function ($query) {
                $query->where('module', 'abstract_of_quotation')
                    ->with('employee:id,firstname,lastname')
                    ->orderBy('sequence');
            },
            'fundCluster:id,name',
            'category:id,name',
        ]);
    }

    // Resolve the record and reload collections
    protected function resolveRecord($key): Model
    {
        $record = parent::resolveRecord($key);
        $this->record = $record;
        $this->loadCustomCollections();
        return $this->record;
    }

    // Handle Livewire hydration
    public function hydrate()
    {
        if ($this->record) {
            $this->loadCustomCollections();
        }
    }

    private function isSupplierEvaluated($responseId): bool
    {
        if (!$responseId) {
            return false;
        }

        return AoqEvaluation::where('procurement_id', $this->record->id)
            ->where('rfq_response_id', $responseId)
            ->exists();
    }

    // Detect lowest bids and mark evaluations
    private function detectLowestBids($record)
    {
        $procurementItems = $record->procurementItems;
        $rfqResponses = $record->rfqResponses;
        
        if ($procurementItems->isEmpty() || $rfqResponses->isEmpty()) {
            return;
        }
        
        // Check for existing tie-breaking record
        $hasTieBreakingRecord = \DB::table('aoq_tie_breaking_records')
            ->where('procurement_id', $record->id)
            ->exists();
        
        $supplierTotals = [];
        foreach ($rfqResponses as $rfqResponse) {
            $failedDocs = AoqEvaluation::where('procurement_id', $record->id)
                ->where('rfq_response_id', $rfqResponse->id)
                ->where('requirement', 'not like', 'quote_%')
                ->where('status', 'fail')
                ->exists();
            
            $totalQuoted = $rfqResponse->quotes->sum('total_value');
            
            $supplierTotals[] = [
                'rfq_response_id' => $rfqResponse->id,
                'total_quoted' => $totalQuoted,
                'disqualified' => $failedDocs,
            ];
        }
        
        usort($supplierTotals, function($a, $b) {
            return $a['total_quoted'] <=> $b['total_quoted'];
        });
        
        // Determine winning supplier
        $winningSupplier = null;
        
        if ($hasTieBreakingRecord) {
            $tieRecord = \DB::table('aoq_tie_breaking_records')
                ->where('procurement_id', $record->id)
                ->first();
            
            if ($tieRecord) {
                $winningSupplier = $tieRecord->winner_rfq_response_id;
            }
        } else {
            // Check for tied suppliers
            $tieInfo = $this->detectTiedSuppliers($record);
            
            if ($tieInfo) {
                // Mark evaluations for tied suppliers
                foreach ($procurementItems as $item) {
                    $quotesForItem = $rfqResponses
                        ->flatMap->quotes
                        ->where('procurement_item_id', $item->id)
                        ->filter(fn($quote) => $quote->unit_value > 0);
                    
                    if ($quotesForItem->isEmpty()) continue;
                    
                    foreach ($quotesForItem as $quote) {
                        $thisSupplierDisqualified = collect($supplierTotals)
                            ->firstWhere('rfq_response_id', $quote->rfq_response_id)['disqualified'] ?? false;
                        
                        AoqEvaluation::updateOrCreate(
                            [
                                'rfq_response_id' => $quote->rfq_response_id,
                                'procurement_id' => $record->id,
                                'requirement' => 'quote_' . $item->id,
                            ],
                            [
                                'status' => $thisSupplierDisqualified ? 'fail' : 'pass',
                                'lowest_bid' => false,
                                'remarks' => $thisSupplierDisqualified 
                                    ? 'Disqualified due to failed document evaluation' 
                                    : 'Awaiting tie-breaking resolution',
                            ]
                        );
                    }
                }
                return;
            } else {
                // Select winner if no tie
                foreach ($supplierTotals as $supplier) {
                    if (!$supplier['disqualified']) {
                        $winningSupplier = $supplier['rfq_response_id'];
                        break;
                    }
                }
            }
        }
        
        // Update evaluations with winner
        if ($winningSupplier) {
            foreach ($procurementItems as $item) {
                $quotesForItem = $rfqResponses
                    ->flatMap->quotes
                    ->where('procurement_item_id', $item->id)
                    ->filter(fn($quote) => $quote->unit_value > 0);
                
                if ($quotesForItem->isEmpty()) continue;
                
                foreach ($quotesForItem as $quote) {
                    $thisSupplierDisqualified = collect($supplierTotals)
                        ->firstWhere('rfq_response_id', $quote->rfq_response_id)['disqualified'] ?? false;
                    
                    $isWinner = $quote->rfq_response_id === $winningSupplier;
                    
                    $remarks = null;
                    if ($thisSupplierDisqualified) {
                        $remarks = 'Disqualified due to failed document evaluation';
                    } elseif ($isWinner) {
                        if ($hasTieBreakingRecord) {
                            $tieRecord = \DB::table('aoq_tie_breaking_records')
                                ->where('procurement_id', $record->id)
                                ->first();
                            $remarks = sprintf(
                                'Winning bid - determined by %s (tied at ₱%s with %d other supplier(s))',
                                $tieRecord->method === 'coin_toss' ? 'coin toss' : 'random draw',
                                number_format($tieRecord->tied_amount, 2),
                                $tieRecord->tied_suppliers_count - 1
                            );
                        } else {
                            $remarks = 'Winning bid - lowest total quoted amount';
                        }
                    }
                    
                    AoqEvaluation::updateOrCreate(
                        [
                            'rfq_response_id' => $quote->rfq_response_id,
                            'procurement_id' => $record->id,
                            'requirement' => 'quote_' . $item->id,
                        ],
                        [
                            'status' => $thisSupplierDisqualified ? 'fail' : 'pass',
                            'lowest_bid' => $isWinner,
                            'remarks' => $remarks,
                        ]
                    );
                }
            }
        }
    }

    // Store tie-breaking record for audit trail
    private function storeTieBreakingRecord($record, $tieInfo, $winner)
    {
        try {
            \DB::table('aoq_tie_breaking_records')->insert([
                'procurement_id' => $record->id,
                'aoq_number' => $record->procurement_id,
                'method' => $tieInfo['method'],
                'tied_amount' => $tieInfo['amount'],
                'tied_suppliers_count' => $tieInfo['count'],
                'tied_suppliers_data' => json_encode($tieInfo['suppliers']),
                'winner_rfq_response_id' => $winner['rfq_response_id'],
                'winner_supplier_name' => $winner['supplier_name'],
                'seed_used' => crc32(time() . $record->id . $record->procurement_id),
                'performed_at' => now(),
                'performed_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Handle error silently
        }
    }

    // Get page title
    public function getTitle(): string
    {
        return "AOQ No. " . ($this->record->procurement_id ?? 'N/A');
    }

    // Detect tied lowest bids among qualified suppliers
    private function detectTiedSuppliers($record): ?array
    {
        $procurementItems = $record->procurementItems;
        $rfqResponses = $record->rfqResponses;
        
        if ($procurementItems->isEmpty() || $rfqResponses->isEmpty()) {
            return null;
        }
        
        // Calculate supplier totals
        $supplierTotals = [];
        foreach ($rfqResponses as $rfqResponse) {
            $failedDocs = AoqEvaluation::where('procurement_id', $record->id)
                ->where('rfq_response_id', $rfqResponse->id)
                ->where('requirement', 'not like', 'quote_%')
                ->where('status', 'fail')
                ->exists();
            
            $totalQuoted = $rfqResponse->quotes->sum('total_value');
            
            $supplierTotals[] = [
                'rfq_response_id' => $rfqResponse->id,
                'supplier_name' => $rfqResponse->supplier?->business_name ?? $rfqResponse->business_name ?? 'Unknown',
                'total_quoted' => $totalQuoted,
                'disqualified' => $failedDocs,
            ];
        }
        
        // Filter qualified suppliers
        $qualifiedSuppliers = collect($supplierTotals)->where('disqualified', false);
        
        if ($qualifiedSuppliers->isEmpty()) {
            return null;
        }
        
        // Find lowest bid and check for ties
        $lowestBid = $qualifiedSuppliers->min('total_quoted');
        $lowestBidders = $qualifiedSuppliers->where('total_quoted', $lowestBid)->values();
        
        if ($lowestBidders->count() > 1) {
            return [
                'count' => $lowestBidders->count(),
                'amount' => $lowestBid,
                'suppliers' => $lowestBidders->toArray(),
                'method' => $lowestBidders->count() === 2 ? 'coin_toss' : 'random_draw'
            ];
        }
        
        return null;
    }

    // Perform tie-breaking using coin toss or random draw
    private function performTieBreaking($tieInfo, $record)
    {
        // Set random seed for reproducibility
        $seed = time() . $record->id . $record->procurement_id;
        mt_srand(crc32($seed));
        
        $suppliers = collect($tieInfo['suppliers']);
        
        if ($tieInfo['method'] === 'coin_toss') {
            // Coin toss for two suppliers
            $winnerIndex = mt_rand(0, 1);
            return $suppliers[$winnerIndex];
        } else {
            // Random draw for multiple suppliers
            $winnerIndex = mt_rand(0, $suppliers->count() - 1);
            return $suppliers[$winnerIndex];
        }
    }

    // Check for unresolved ties
    private function hasUnresolvedTies($record): bool
    {
        $tieInfo = $this->detectTiedSuppliers($record);
        
        if (!$tieInfo) {
            return false;
        }
        
        return !\DB::table('aoq_tie_breaking_records')
            ->where('procurement_id', $record->id)
            ->exists();
    }

    // Get RFQ response form schema
    protected function getRfqResponseFormSchema(): array
    {
        return [
            FormSection::make('RFQ Response Details')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Select::make('supplier_id')
                                ->label('Supplier')
                                ->options(function (Get $get) {
                                    $rfq = Procurement::where('parent_id', $this->record->parent_id)
                                        ->where('module', 'request_for_quotation')
                                        ->first();

                                    if (!$rfq) return [];

                                    // Get all distributed suppliers
                                    $distributed = RfqDistribution::where('procurement_id', $rfq->id)
                                        ->with('supplier')
                                        ->get();

                                    // Get all suppliers who already submitted a response
                                    $respondedSupplierIds = RfqResponse::where('procurement_id', $rfq->id)
                                        ->pluck('supplier_id')
                                        ->toArray();

                                    // Filter out suppliers who already responded
                                    $availableSuppliers = $distributed->filter(function ($dist) use ($respondedSupplierIds) {
                                        return !in_array($dist->supplier_id, $respondedSupplierIds);
                                    });

                                    $options = $availableSuppliers->pluck('supplier.business_name', 'supplier_id')->toArray();

                                    // If editing an existing response, ensure the current supplier is included
                                    $currentId = $get('supplier_id');
                                    if ($currentId && !isset($options[$currentId])) {
                                        $supplier = Supplier::find($currentId);
                                        if ($supplier) {
                                            $options[$currentId] = $supplier->business_name;
                                        }
                                    }

                                    return $options;
                                })
                                ->required()
                                ->reactive()
                                ->searchable()
                                ->preload()
                                ->disabled(fn (Get $get) => filled($get('supplier_id')))
                                ->dehydrated(fn (Get $get) => filled($get('supplier_id')))
                                ->afterStateUpdated(function (callable $set, $state) {
                                    if ($state) {
                                        $supplier = Supplier::find($state);
                                        if ($supplier) {
                                            $set('business_name', $supplier->business_name);
                                            $set('business_address', $supplier->business_address);
                                            $set('contact_no', $supplier->contact_no);
                                            $set('email_address', $supplier->email_address);
                                            $set('tin', $supplier->tin);
                                            $set('vat', $supplier->vat);
                                            $set('nvat', $supplier->nvat);
                                            $set('philgeps_reg_no', $supplier->philgeps_reg_no);
                                            $set('lbp_account_name', $supplier->lbp_account_name);
                                            $set('lbp_account_number', $supplier->lbp_account_number);

                                            // Auto-fill documents from supplier's existing files
                                            $procurement = $this->record;
                                            $pr = $procurement->parent->children()->where('module', 'purchase_request')->first();
                                            $abc = $pr?->grand_total ?? 0;
                                            $categoryName = $procurement->category?->name ?? '';

                                            $requirements = $this->getRequiredDocuments($categoryName, $abc);

                                            $availableDocs = $supplier->getAvailableDocuments();
                                            foreach ($requirements as $requirement) {
                                                if (isset($availableDocs[$requirement])) {
                                                    $path = $availableDocs[$requirement];
                                                    if (Storage::disk('public')->exists($path)) {
                                                        $set("documents.{$requirement}", [$path]);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }),
                            
                            TextInput::make('submitted_by')
                                ->label('Submitted By')
                                ->required()
                                ->maxLength(255),
                        ]),
                    
                    Forms\Components\Grid::make(2)
                        ->schema([
                            TextInput::make('designation')
                                ->label('Designation')
                                ->required()
                                ->maxLength(255),
                            DatePicker::make('submitted_date')
                                ->label('Submission Date')
                                ->required()
                                ->default(now()),
                        ]),
                    
                    Forms\Components\Grid::make(2)
                        ->schema([
                            TextInput::make('business_name')
                                ->label('Business Name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('philgeps_reg_no')
                                ->label('PhilGEPS Registration No.')
                                ->maxLength(255)
                                ->nullable(),
                        ]),
                    
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Textarea::make('business_address')
                                ->label('Business Address')
                                ->required()
                                ->rows(4),
                            Forms\Components\Group::make()
                                ->schema([
                                    TextInput::make('tin')
                                        ->label('TIN')
                                        ->maxLength(255)
                                        ->nullable(),
                                    Checkbox::make('vat')
                                        ->label('VAT Registered')
                                        ->live()
                                        ->afterStateUpdated(function (callable $set, $state) {
                                            if ($state) {
                                                $set('nvat', false);
                                            }
                                        }),
                                    Checkbox::make('nvat')
                                        ->label('NVAT Registered')
                                        ->live()
                                        ->afterStateUpdated(function (callable $set, $state) {
                                            if ($state) {
                                                $set('vat', false);
                                            }
                                        }),
                                ]),
                        ]),
                    
                    Forms\Components\Grid::make(2)
                        ->schema([
                            TextInput::make('contact_no')
                                ->label('Contact Number')
                                ->tel()
                                ->required()
                                ->maxLength(255),
                            TextInput::make('lbp_account_name')
                                ->label('LBP Account Name')
                                ->maxLength(255)
                                ->nullable(),
                        ]),
                    
                    Forms\Components\Grid::make(2)
                        ->schema([
                            TextInput::make('email_address')
                                ->label('Email Address')
                                ->email()
                                ->required()
                                ->maxLength(255),
                            TextInput::make('lbp_account_number')
                                ->label('LBP Account Number')
                                ->maxLength(255)
                                ->nullable(),
                        ]),
                ])
                ->columns(2),

            FormSection::make('Original RFQ Document')
                ->description('Upload a copy of the original RFQ document submitted by the supplier as proof')
                ->schema([
                    FileUpload::make('rfq_document')
                        ->label('RFQ Document Copy')
                        ->disk('public')
                        ->directory('rfq-original-documents')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(10240)
                        ->required()
                        ->helperText('Upload the original RFQ document submitted by the supplier')
                        ->getUploadedFileNameForStorageUsing(function ($file, Get $get) {
                            $supplierId = $get('supplier_id') ?? 'new';
                            $timestamp = time();
                            $extension = $file->getClientOriginalExtension();
                            return "supplier_{$supplierId}_rfq_document_{$timestamp}.{$extension}";
                        })
                        ->afterStateHydrated(function ($component, $state) {
                            if (is_string($state) && !empty($state)) {
                                $component->state([$state]);
                            }
                        })
                        ->dehydrateStateUsing(function ($state) {
                            if (is_array($state) && !empty($state)) {
                                return isset($state[0]) ? $state[0] : reset($state);
                            }
                            return is_string($state) ? $state : null;
                        })
                        ->deleteUploadedFileUsing(function ($file) {
                            if (is_string($file)) {
                                Storage::disk('public')->delete($file);
                            }
                        })
                        ->deletable(true)
                        ->downloadable(true)
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->collapsible(),

            FormSection::make('Supplier Documents')
                ->description('Documents will be pre-filled from supplier records when available. You can replace them with updated versions.')
                ->schema($this->getDocumentUploads())
                ->columns(1)
                ->collapsible(),

            FormSection::make('Item Quotes')
                ->schema([
                    Repeater::make('quotes')
                        ->schema([
                            Forms\Components\Hidden::make('procurement_item_id')
                                ->required(),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Group::make()
                                        ->schema([
                                            TextInput::make('item_no')
                                                ->label(fn () => $this->record->parent->children()->where('module', 'purchase_request')->first()->basis === 'lot' ? 'Lot No.' : 'Item No.')
                                                ->numeric()
                                                ->readOnly(),
                                            TextInput::make('unit')
                                                ->label('Unit')
                                                ->readOnly(),
                                            Textarea::make('item_description')
                                                ->label(fn () => $this->record->parent->children()->where('module', 'purchase_request')->first()->basis === 'lot' ? 'Lot Description' : 'Item Description')
                                                ->readOnly()
                                                ->rows(2),
                                        ])
                                        ->columnSpan(1),

                                    Forms\Components\Group::make()
                                        ->schema([
                                            TextInput::make('unit_value')
                                                ->label('Unit Value')
                                                ->numeric()
                                                ->prefix('₱')
                                                ->required()
                                                ->minValue(0)
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                    $quantity = $get('quantity') ?? 1;
                                                    $set('total_value', (float) $state * (float) $quantity);
                                                }),
                                            TextInput::make('total_value')
                                                ->label('Total Value')
                                                ->numeric()
                                                ->prefix('₱')
                                                ->required()
                                                ->minValue(0)
                                                ->readOnly(),
                                            Textarea::make('specifications')
                                                ->label('Specifications (Brand/Model/Others)')
                                                ->rows(2)
                                                ->required(),
                                        ])
                                        ->columnSpan(1),
                                ]),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Group::make()
                                        ->schema([
                                            TextInput::make('quantity')
                                                ->label('Quantity')
                                                ->numeric()
                                                ->readOnly(),
                                            TextInput::make('total_cost')
                                                ->label('Total ABC')
                                                ->numeric()
                                                ->prefix('₱')
                                                ->readOnly(),
                                        ])
                                        ->columnSpan(1),

                                    Forms\Components\Group::make()
                                        ->schema([
                                            Toggle::make('statement_of_compliance')
                                                ->label('Complies')
                                                ->required()
                                                ->default(true),
                                        ])
                                        ->columnSpan(1)
                                        ->extraAttributes(['class' => 'ml-auto']),
                                ]),
                        ])
                        ->default(function () {
                            $procurementId = $this->getProcurementIdForItems();
                            return ProcurementItem::where('procurement_id', $procurementId)
                                ->orderBy('sort')
                                ->get()
                                ->map(fn ($item) => [
                                    'procurement_item_id' => $item->id,
                                    'item_no' => $item->sort,
                                    'unit' => $item->unit,
                                    'quantity' => $item->quantity,
                                    'item_description' => $item->item_description,
                                    'total_cost' => $item->total_cost,
                                    'statement_of_compliance' => true,
                                    'specifications' => null,
                                    'unit_value' => null,
                                    'total_value' => null,
                                ])
                                ->toArray();
                        })
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false),
                ]),
        ];
    }

    protected function getProcurementIdForItems(): int
    {
        $rfq = Procurement::where('parent_id', $this->record->parent_id)
            ->where('module', 'request_for_quotation')
            ->first();
        $purchaseRequest = Procurement::where('parent_id', $this->record->parent_id)
            ->where('module', 'purchase_request')
            ->first();
        return $purchaseRequest ? $purchaseRequest->id : $this->record->parent_id;
    }

    protected function getRequiredDocuments(string $categoryName, float $abc): array
    {
        $requirements = ['mayors_permit', 'philgeps_certificate'];
        if (str_contains(strtolower($categoryName), 'infrastructure')) $requirements[] = 'pcab_license';
        if (str_contains(strtolower($categoryName), 'consulting')) $requirements[] = 'professional_license_cv';
        if (str_contains(strtolower($categoryName), 'catering') || str_contains(strtolower($categoryName), 'printing')) $requirements[] = 'terms_conditions_tech_specs';
        if ($abc > 500000) $requirements[] = 'tax_return';
        $requirements[] = 'omnibus_sworn_statement';
        return $requirements;
    }

    protected function getDocumentUploads(): array
    {
        $procurement = $this->record;
        $abc = $procurement->parent->children()->where('module', 'purchase_request')->first()->grand_total ?? 0;
        $categoryName = $procurement->category->name ?? '';
        $requirements = $this->getRequiredDocuments($categoryName, $abc);
        $schema = [];

        foreach ($requirements as $requirement) {
            $schema[] = FileUpload::make("documents.{$requirement}")
                ->label(ucwords(str_replace('_', ' ', $requirement)))
                ->disk('public')
                ->directory('supplier-documents')
                ->acceptedFileTypes(['application/pdf'])
                ->maxSize(10240)
                ->required($requirement !== 'omnibus_sworn_statement' || $abc >= 50000)
                ->helperText($requirement === 'omnibus_sworn_statement' && $abc < 50000 ? 'Optional for ABC below 50k' : 'Required')
                ->getUploadedFileNameForStorageUsing(function ($file, Get $get) use ($requirement) {
                    $supplierId = $get('supplier_id') ?? 'new';
                    $extension = $file->getClientOriginalExtension();
                    return "supplier_{$supplierId}_{$requirement}_" . time() . ".{$extension}";
                })
                ->afterStateHydrated(fn ($component, $state) => is_string($state) && $state ? $component->state([$state]) : null)
                ->dehydrateStateUsing(fn ($state) => is_array($state) ? (reset($state) ?: null) : (is_string($state) ? $state : null))
                ->deletable()
                ->downloadable();
        }
        return $schema;
    }

    public function editResponseModalAction(): Action
    {
        return Action::make('editResponseModal')
            ->label('Edit')
            ->modalHeading('Edit RFQ Response')
            ->modalSubmitActionLabel('Update Response')
            ->color('primary')
            ->icon('heroicon-o-pencil')
            ->modalWidth('7xl')
            ->fillForm(fn (array $arguments) => $this->fillEditForm($arguments['responseId'] ?? null))
            ->form($this->getRfqResponseFormSchema())
            ->action(fn (array $data, array $arguments) => $this->updateResponse($data, $arguments['responseId'] ?? null))
            ->visible(fn (array $arguments) => !$this->isSupplierEvaluated($arguments['responseId'] ?? null))
            ->tooltip(fn (array $arguments) => $this->isSupplierEvaluated($arguments['responseId'] ?? null) ? 'Cannot edit: Supplier has been evaluated' : null);
    }

    public function deleteResponseModalAction(): Action
    {
        return Action::make('deleteResponseModal')
            ->label('Delete')
            ->modalHeading('Delete RFQ Response')
            ->modalDescription('Are you sure you want to delete this supplier response? This action cannot be undone.')
            ->modalSubmitActionLabel('Delete')
            ->requiresConfirmation()
            ->color('danger')
            ->icon('heroicon-o-trash')
            ->action(fn (array $arguments) => $this->deleteResponse($arguments['responseId'] ?? null))
            ->visible(fn (array $arguments) => !$this->isSupplierEvaluated($arguments['responseId'] ?? null))
            ->tooltip(fn (array $arguments) => $this->isSupplierEvaluated($arguments['responseId'] ?? null) ? 'Cannot delete: Supplier has been evaluated' : null);
    }

    private function fillEditForm($responseId)
    {
        if (!$responseId) return [];
        $record = RfqResponse::with('supplier')->find($responseId);
        if (!$record) return [];

        $data = $record->toArray();

        $data['supplier_id'] = $record->supplier_id;

        $supplier = $record->supplier;

        if ($supplier) {
            foreach ([
                'business_name', 'business_address', 'contact_no', 'email_address',
                'tin', 'vat', 'nvat', 'philgeps_reg_no',
                'lbp_account_name', 'lbp_account_number'
            ] as $field) {
                $data[$field] = $data[$field] ?? $supplier->{$field};
            }
        }

        // Handle documents safely
        $docs = [];

        if (!empty($record->documents)) {
            $decoded = is_string($record->documents)
                ? json_decode($record->documents, true)
                : $record->documents;

            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    // Only include if value is a non-empty string (valid file path)
                    if (is_string($value) && $value !== '') {
                        $docs[$key] = [$value];
                    }
                }
            }
        }

        $data['documents'] = $docs; // Always an array

        $data['rfq_document'] = $record->rfq_document;

        // Quotes
        $itemsProcurementId = $this->getProcurementIdForItems();
        $items = ProcurementItem::where('procurement_id', $itemsProcurementId)->orderBy('sort')->get();

        $data['quotes'] = $items->map(function ($item) use ($record) {
            $quote = $record->quotes->firstWhere('procurement_item_id', $item->id);
            return [
                'id' => $quote?->id,
                'procurement_item_id' => $item->id,
                'item_no' => $item->sort,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'item_description' => $item->item_description,
                'total_cost' => $item->total_cost,
                'statement_of_compliance' => $quote?->statement_of_compliance ?? true,
                'specifications' => $quote?->specifications,
                'unit_value' => $quote?->unit_value,
                'total_value' => $quote?->total_value,
            ];
        })->toArray();

        return $data;
    }

    private function updateResponse($data, $responseId)
    {
        if (!$responseId) return;
        $rfqResponse = RfqResponse::find($responseId);
        if (!$rfqResponse) return;

        $supplier = Supplier::find($data['supplier_id']);
        if ($supplier) {
            if (isset($data['documents'])) {
                $this->updateSupplierDocuments($supplier, $data['documents']);
            }

            $supplier->update(collect($data)->only([
                'business_name', 'business_address', 'contact_no', 'email_address',
                'tin', 'vat', 'nvat', 'philgeps_reg_no',
                'lbp_account_name', 'lbp_account_number'
            ])->toArray());
        }

        $quotes = $data['quotes'] ?? [];
        unset($data['quotes']);
        $rfqResponse->update($data);

        foreach ($quotes as $quoteData) {
            if (isset($quoteData['procurement_item_id'])) {
                $rfqResponse->quotes()->updateOrCreate(
                    ['procurement_item_id' => $quoteData['procurement_item_id']],
                    collect($quoteData)->only([
                        'unit_value', 'total_value', 'specifications', 'statement_of_compliance'
                    ])->toArray()
                );
            }
        }

        Notification::make()->title('Response Updated')->success()->send();
        redirect()->route('filament.admin.resources.procurements.view-aoq', $this->record->parent_id);
    }

    private function deleteResponse($responseId)
    {
        if (!$responseId) return;
        $rfqResponse = RfqResponse::find($responseId);
        if (!$rfqResponse) {
            Notification::make()->title('Not Found')->danger()->send();
            return;
        }

        if ($rfqResponse->rfq_document && Storage::disk('public')->exists($rfqResponse->rfq_document)) {
            Storage::disk('public')->delete($rfqResponse->rfq_document);
        }

        $supplierName = $rfqResponse->supplier?->business_name ?? 'Unknown';
        $rfqResponse->delete();

        ActivityLogger::log('Supplier Response Deleted', "Response from $supplierName deleted by " . auth()->user()?->name);
        Notification::make()->title('Response Deleted')->success()->send();
        redirect()->route('filament.admin.resources.procurements.view-aoq', $this->record->parent_id);
    }

    /**
     * Update supplier document columns with the new uploaded files.
     * Old files are deleted when they differ from the new path.
     */
    private function updateSupplierDocuments(Supplier $supplier, array $documents): void
    {
        foreach ($documents as $requirement => $filePath) {
            if (!$filePath || !is_string($filePath)) {
                continue;
            }

            $oldPath = $supplier->{$requirement};

            // Delete the previous file if it exists and is different
            if ($oldPath && $oldPath !== $filePath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            // Save the new path in the supplier table
            $supplier->update([$requirement => $filePath]);
        }
    }

    // Define infolist schema for displaying AOQ details
    public function infolist(Infolist $infolist): Infolist
    {
        $this->record->load([
            'rfqResponses.aoqEvaluations' => function ($query) {
                $query->where('procurement_id', $this->record->id);
            }
        ]);

        // Ensure custom collections are loaded to fetch $pr
        $this->loadCustomCollections();

        // Get rejection details if rejected
        $rejectionApproval = null;
        if ($this->record->status === 'Rejected') {
            $rejectionApproval = $this->record->approvals()
                ->where('module', 'abstract_of_quotation')
                ->where('status', 'Rejected')
                ->with('employee')
                ->orderBy('action_at', 'desc')
                ->first();
        }

        $schema = [];

        // Add rejection notice section if rejected
        if ($rejectionApproval) {
            $schema[] = Section::make('AOQ Rejected')
                ->schema([
                    TextEntry::make('rejection_remarks')
                        ->label('Rejection Remarks')
                        ->state($rejectionApproval->remarks ?? 'No remarks provided')
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->extraAttributes(['class' => 'bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500']);
        }

        // Safely fetch Purchase Request, fallback to direct query if not set
        $pr = $this->record->procurementItems->isNotEmpty()
            ? Procurement::where('parent_id', $this->record->parent_id)
                ->where('module', 'purchase_request')
                ->first()
            : null;

        // Determine labels based on basis, default to 'item' if $pr is null
        $isLot = $pr && $pr->basis === 'lot';
        $numberLabel = $isLot ? 'Lot No.' : 'Item No.';
        $descriptionLabel = $isLot ? 'Lot Description' : 'Item Description';

        return $infolist
            ->schema([
                Section::make('Abstract of Quotation Details')
                    ->schema([
                        TextEntry::make('procurement_id')->label('AOQ No.'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Pending'   => 'warning',
                                'Evaluated' => 'info',
                                'Approved'  => 'success',
                                'Locked'    => 'danger',
                                'Rejected'  => 'danger',
                                default     => 'gray',
                            })
                            ->getStateUsing(function ($record) {
                                $approvals = $record->approvals;
                                
                                if ($approvals->isEmpty()) {
                                    return 'Pending';
                                } elseif ($approvals->contains('status', 'Rejected')) {
                                    return 'Rejected';
                                } elseif ($approvals->every(fn ($approval) => $approval->status === 'Approved')) {
                                    return 'Approved';
                                }
                                return $record->status;
                            }),
                        TextEntry::make('evaluation_status')
                            ->label('Evaluation Status')
                            ->badge()
                            ->color(function ($record): string {
                                $totalDocs = $record->rfqResponses->sum(function($r) {
                                    $docCount = is_array($r->documents) ? count($r->documents) : 0;
                                    return $docCount + (!empty($r->rfq_document) ? 1 : 0);
                                });
                                
                                $evaluatedDocs = AoqEvaluation::where('procurement_id', $record->id)
                                    ->where(function($query) {
                                        $query->where('requirement', 'not like', 'quote_%')
                                            ->orWhere('requirement', 'rfq_document');
                                    })
                                    ->count();
                                
                                if ($totalDocs === 0) return 'gray';
                                if ($evaluatedDocs >= $totalDocs) return 'success';
                                return 'warning';
                            })
                            ->getStateUsing(function ($record) {
                                $totalDocs = $record->rfqResponses->sum(function($r) {
                                    $docCount = is_array($r->documents) ? count($r->documents) : 0;
                                    return $docCount + (!empty($r->rfq_document) ? 1 : 0);
                                });
                                
                                $evaluatedDocs = AoqEvaluation::where('procurement_id', $this->record->id)
                                    ->where(function($query) {
                                        $query->where('requirement', 'not like', 'quote_%')
                                            ->orWhere('requirement', 'rfq_document');
                                    })
                                    ->count();
                                        
                                if ($totalDocs === 0) return 'No Documents';
                                return $evaluatedDocs >= $totalDocs ? 'Complete' : "Partial ({$evaluatedDocs}/{$totalDocs})";
                            }),
                        TextEntry::make('created_at')->label('Date Filed')->date('Y-m-d'),
                        TextEntry::make('title')->label('Title/Purpose'),
                        TextEntry::make('requested_by')
                            ->label('End User')
                            ->getStateUsing(fn ($record) => $record->requester?->full_name ?? 'Not set'),
                        TextEntry::make('procurement_type')
                            ->badge()
                            ->color(fn (string $state) => $state === 'small_value_procurement' ? 'info' : 'primary')
                            ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state)))
                            ->label('Mode of Procurement'),
                        TextEntry::make('fundCluster.name')->label('Fund Cluster'),
                        TextEntry::make('category.name')->label('Category'),
                        TextEntry::make('grand_total')
                            ->label('Approved Budget for Contract (ABC)')
                            ->money('PHP')
                            ->weight('bold')
                            ->getStateUsing(fn ($record) => $record->procurementItems->sum('total_cost')),
                        TextEntry::make('delivery_period')
                            ->label('Delivery Period')
                            ->state(function ($record) {
                                $rfq = Procurement::where('parent_id', $record->parent_id)
                                    ->where('module', 'request_for_quotation')
                                    ->first();
                                if ($rfq && $rfq->delivery_mode === 'days' && $rfq->delivery_value) {
                                    return "Within {$rfq->delivery_value} calendar days upon receipt of Purchase Order";
                                }
                                if ($rfq && $rfq->delivery_mode === 'date' && $rfq->delivery_value) {
                                    return Carbon::parse($rfq->delivery_value)->format('F j, Y');
                                }
                                return 'Not set';
                            }),
                        TextEntry::make('bid_opening_datetime')
                            ->label('Date and Time of Bid Opening')
                            ->formatStateUsing(fn ($state) => $state ? $state->format('Y-m-d h:i A') : 'Not scheduled')
                            ->badge()
                            ->color(fn ($state) => $state ? (Carbon::now()->greaterThanOrEqualTo($state) ? 'success' : 'warning') : 'danger'),
                    ])
                    ->columns(4),

                Section::make('Procurement Items')
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        RepeatableEntry::make('procurementItems')
                            ->label('')
                            ->schema([
                                TextEntry::make('sort')->label($numberLabel),
                                TextEntry::make('item_description')->label($descriptionLabel),
                                TextEntry::make('quantity')->label('Quantity'),
                                TextEntry::make('unit')->label('Unit'),
                                TextEntry::make('unit_cost')->label('Unit Cost (ABC)')->money('PHP'),
                                TextEntry::make('total_cost')->label('Total Cost (ABC)')->money('PHP'),
                            ])
                            ->columns(6),
                        TextEntry::make('no_items')
                            ->label('')
                            ->default('No items listed')
                            ->hidden(fn ($record) => $record->procurementItems->count() > 0),
                    ]),

                // Supplier Responses Section (moved before Supplier Evaluations)
                Section::make('Supplier Responses')
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        TextEntry::make('response_status')
                            ->label('')
                            ->getStateUsing(function ($record) {
                                if (is_null($record->bid_opening_datetime)) {
                                    return 'Please set the Date and Time of Bid Opening first.';
                                }
                                if (Carbon::now()->lessThan($record->bid_opening_datetime)) {
                                    return 'Creating response can start on ' . $record->bid_opening_datetime->format('Y-m-d h:i A') . '.';
                                }
                                return null;
                            })
                            ->visible(function ($record) {
                                return is_null($record->bid_opening_datetime) || 
                                    Carbon::now()->lessThan($record->bid_opening_datetime);
                            })
                            ->extraAttributes(['class' => 'text-yellow-600 dark:text-yellow-400 font-semibold text-center']),
                        
                        \Filament\Infolists\Components\ViewEntry::make('rfq_responses_table')
                            ->label('')
                            ->view('filament.resources.procurement-resource.pages.rfq-responses-table')
                            ->state($this->record)
                            ->visible(function ($record) {
                                return !is_null($record->bid_opening_datetime) && 
                                    Carbon::now()->greaterThanOrEqualTo($record->bid_opening_datetime);
                            })
                    ])
                    ->columnSpanFull(),

                Section::make('Supplier Evaluations')
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        TextEntry::make('evaluation_blocked')
                            ->label('')
                            ->getStateUsing(function ($record) {
                                if (is_null($record->bid_opening_datetime)) {
                                    return 'Please set the Date and Time of Bid Opening to evaluate suppliers.';
                                }
                                if (Carbon::now()->lessThan($record->bid_opening_datetime)) {
                                    return 'Evaluations can start on ' . $record->bid_opening_datetime->format('Y-m-d h:i A') . '.';
                                }
                                
                                // Show "No RFQ responses" if no responses exist after bid opening
                                if ($record->rfqResponses->isEmpty()) {
                                    return 'No RFQ responses documented yet.';
                                }
                                
                                return null;
                            })
                            ->visible(function ($record) {
                                return is_null($record->bid_opening_datetime) || 
                                    Carbon::now()->lessThan($record->bid_opening_datetime) ||
                                    ($record->rfqResponses->isEmpty() && Carbon::now()->greaterThanOrEqualTo($record->bid_opening_datetime));
                            })
                            ->formatStateUsing(fn ($state) => $state)
                            ->extraAttributes(['class' => 'text-red-600 dark:text-red-400 font-semibold text-center']),
                        
                        TextEntry::make('supplier_list')
                            ->label('')
                            ->html()
                            ->getStateUsing(function ($record) use ($isLot, $numberLabel, $descriptionLabel) {
                                // Only show supplier list after bid opening AND if responses exist
                                if (is_null($record->bid_opening_datetime) || 
                                    Carbon::now()->lessThan($record->bid_opening_datetime) ||
                                    $record->rfqResponses->isEmpty()) {
                                    return '';
                                }

                                $record->load([
                                    'rfqResponses.aoqEvaluations' => function ($query) use ($record) {
                                        $query->where('procurement_id', $record->id);
                                    }
                                ]);

                                $html = '';
                                $hasAnyEvaluations = AoqEvaluation::where('procurement_id', $record->id)->exists();

                                // Compute evaluation completeness
                                $totalDocs = $record->rfqResponses->sum(function($r) {
                                    $docCount = is_array($r->documents) ? count($r->documents) : 0;
                                    return $docCount + (!empty($r->rfq_document) ? 1 : 0);
                                });
                                
                                $evaluatedDocs = AoqEvaluation::where('procurement_id', $record->id)
                                    ->where(function($query) {
                                        $query->where('requirement', 'not like', 'quote_%')
                                            ->orWhere('requirement', 'rfq_document');
                                    })
                                    ->count();
                                
                                $evaluationComplete = $totalDocs > 0 && $evaluatedDocs >= $totalDocs;

                                foreach ($record->rfqResponses as $rfqResponse) {
                                    $supplierName = $rfqResponse->supplier?->business_name ?? $rfqResponse->business_name ?? 'Unknown Supplier';

                                    $evaluations = AoqEvaluation::where('procurement_id', $record->id)
                                        ->where('rfq_response_id', $rfqResponse->id)
                                        ->where(function($query) {
                                            $query->where('requirement', 'not like', 'quote_%')
                                                ->orWhere('requirement', 'rfq_document');
                                        })
                                        ->get();

                                    $docEvals = $evaluations->keyBy('requirement');
                                    $hasFailedDocs = $evaluations->where('status', 'fail')->isNotEmpty();

                                    // Only show winning bids if evaluations are complete
                                    $hasWinningBids = $evaluationComplete && $hasAnyEvaluations && AoqEvaluation::where('procurement_id', $record->id)
                                        ->where('rfq_response_id', $rfqResponse->id)
                                        ->where('lowest_bid', true)
                                        ->exists();

                                    $html .= '<div class="border rounded-lg p-6 mb-6 bg-white dark:bg-gray-800">';
                                    $html .= '<div class="flex items-center justify-between mb-4">';
                                    $html .= '<h3 class="text-xl font-bold">' . e($supplierName) . '</h3>';

                                    if ($hasFailedDocs) {
                                        $html .= '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">❌ DISQUALIFIED</span>';
                                    } elseif ($hasWinningBids) {
                                        $html .= '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">🏆 WINNING BID</span>';
                                    }

                                    $html .= '</div>';

                                    $html .= '<div class="mb-6">';
                                    $html .= '<details class="border rounded-lg" open>';
                                    $html .= '<summary class="cursor-pointer p-4 font-semibold bg-gray-50 dark:bg-gray-700">Document Evaluation</summary>';
                                    $html .= '<div class="p-4">';
                                    
                                    $hasDocuments = !empty($rfqResponse->rfq_document) || (is_array($rfqResponse->documents) && !empty($rfqResponse->documents));

                                    if ($hasDocuments) {
                                        $html .= '<div class="overflow-x-auto"><table class="w-full divide-y divide-gray-200 dark:divide-gray-700" style="table-layout: fixed;">';
                                        $html .= '<thead class="bg-gray-50 dark:bg-gray-700">';
                                        $html .= '<tr>';
                                        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 20%;">Document Type</th>';
                                        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 30%;">Document</th>';
                                        $html .= '<th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 12%;">Status</th>';
                                        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 38%;">Remarks</th>';
                                        $html .= '</tr>';
                                        $html .= '</thead>';
                                        $html .= '<tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">';
                                        
                                        if (!empty($rfqResponse->rfq_document)) {
                                            $rfqEval = $docEvals->get('rfq_document');
                                            if (!$rfqEval) {
                                                $rfqEval = AoqEvaluation::where('procurement_id', $record->id)
                                                    ->where('rfq_response_id', $rfqResponse->id)
                                                    ->whereIn('requirement', ['rfq_document', 'original_rfq_document'])
                                                    ->first();
                                            }

                                            $status = $rfqEval?->status ?? 'pending';
                                            $remarks = $rfqEval?->remarks ?? 'No remarks';

                                            $statusBadge = match($status) {
                                                'pass' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">✓ Pass</span>',
                                                'fail' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">✗ Fail</span>',
                                                default => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">⏳ Pending</span>',
                                            };

                                            $disk = Storage::disk('public');
                                            $rfqPath = $rfqResponse->rfq_document;
                                            $pathExists = filled($rfqPath) && $disk->exists($rfqPath);
                                            $documentUrl = $pathExists ? $disk->url($rfqPath) : '#';
                                            $filename = basename($rfqPath);
                                            $linkClass = $pathExists ? 'text-primary-600 hover:underline' : 'text-gray-500 cursor-not-allowed';
                                            $linkText = $pathExists ? e($filename) : e($filename) . ' (File not found)';

                                            $html .= '<tr class="bg-blue-50 dark:bg-blue-900/20">';
                                            $html .= '<td class="px-4 py-3 text-sm font-semibold align-top">Original RFQ Document</td>';
                                            $html .= '<td class="px-4 py-3 text-sm align-top" style="word-wrap: break-word; word-break: break-word;"><a href="' . $documentUrl . '" target="_blank" class="' . $linkClass . '">' . $linkText . '</a></td>';
                                            $html .= '<td class="px-4 py-3 text-sm text-center align-top">' . $statusBadge . '</td>';
                                            $html .= '<td class="px-4 py-3 text-sm align-top" style="word-wrap: break-word; word-break: break-word;">' . e($remarks) . '</td>';
                                            $html .= '</tr>';
                                        }

                                        if (is_array($rfqResponse->documents) && !empty($rfqResponse->documents)) {
                                            foreach ($rfqResponse->documents as $requirement => $path) {
                                                $normalizedReq = $this->normalizeRequirement($requirement);
                                                $eval = $docEvals->get($normalizedReq);
                                                $status = $eval?->status ?? 'pending';
                                                $remarks = $eval?->remarks ?? 'No remarks';

                                                $statusBadge = match($status) {
                                                    'pass' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">✓ Pass</span>',
                                                    'fail' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">✗ Fail</span>',
                                                    default => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">⏳ Pending</span>',
                                                };

                                                $disk = Storage::disk('public');
                                                $docPath = $path;
                                                $pathExists = filled($docPath) && is_string($docPath) && $disk->exists($docPath);
                                                $documentUrl = $pathExists ? $disk->url($docPath) : '#';
                                                $filename = basename($docPath);
                                                $linkClass = $pathExists ? 'text-primary-600 hover:underline' : 'text-gray-500 cursor-not-allowed';
                                                $linkText = $pathExists ? e($filename) : e($filename) . ' (File not found)';

                                                $html .= '<tr>';
                                                $html .= '<td class="px-4 py-3 text-sm align-top">' . ucwords(str_replace('_', ' ', $requirement)) . '</td>';
                                                $html .= '<td class="px-4 py-3 text-sm align-top" style="word-wrap: break-word; word-break: break-word;"><a href="' . $documentUrl . '" target="_blank" class="' . $linkClass . '">' . $linkText . '</a></td>';
                                                $html .= '<td class="px-4 py-3 text-sm text-center align-top">' . $statusBadge . '</td>';
                                                $html .= '<td class="px-4 py-3 text-sm align-top" style="word-wrap: break-word; word-break: break-word;">' . e($remarks) . '</td>';
                                                $html .= '</tr>';
                                            }
                                        }

                                        $html .= '</tbody></table></div>';
                                    } else {
                                        $html .= '<p class="text-gray-500">No documents submitted</p>';
                                    }

                                    $html .= '</div></details></div>';

                                    $html .= '<div class="mb-6">';
                                    $html .= '<details class="border rounded-lg" open>';
                                    $html .= '<summary class="cursor-pointer p-4 font-semibold bg-gray-50 dark:bg-gray-700">Quote Comparison</summary>';
                                    $html .= '<div class="p-4">';

                                    if ($rfqResponse->quotes->count() > 0) {
                                        $html .= '<div class="overflow-x-auto"><table class="w-full divide-y divide-gray-200 dark:divide-gray-700" style="table-layout: fixed;">';
                                        $html .= '<thead class="bg-gray-50 dark:bg-gray-700"><tr>';
                                        $html .= '<th class="px-2 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 6%;">' . $numberLabel . '</th>';
                                        $html .= '<th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 22%;">' . $descriptionLabel . '</th>';
                                        $html .= '<th class="px-2 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 6%;">Qty</th>';
                                        $html .= '<th class="px-2 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 8%;">Unit</th>';
                                        $html .= '<th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 13%;">ABC Unit</th>';
                                        $html .= '<th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 13%;">ABC Total</th>';
                                        $html .= '<th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 14%;">Unit Price</th>';
                                        $html .= '<th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 18%;">Total</th>';
                                        $html .= '</tr></thead>';
                                        $html .= '<tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">';

                                        foreach ($rfqResponse->quotes as $quote) {
                                            if (!$quote->procurementItem) continue;
                                            
                                            $item = $quote->procurementItem;
                                            $evaluation = AoqEvaluation::where('procurement_id', $record->id)
                                                ->where('rfq_response_id', $rfqResponse->id)
                                                ->where('requirement', 'quote_' . $item->id)
                                                ->first();
                                            $isLowest = $evaluation?->lowest_bid ?? false;

                                            $html .= '<tr class="' . ($isLowest ? 'bg-green-50 dark:bg-green-900/20' : '') . '">';
                                            $html .= '<td class="px-2 py-3 text-sm text-center align-top">' . e($item->sort) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm align-top" style="word-wrap: break-word; word-break: break-word;">' . e($item->item_description) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-center align-top">' . e($item->quantity) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-center align-top">' . e($item->unit) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-right align-top">₱' . number_format($item->unit_cost, 2) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-right align-top">₱' . number_format($item->total_cost, 2) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-right font-semibold align-top">₱' . number_format($quote->unit_value, 2) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-right font-semibold align-top">₱' . number_format($quote->total_value, 2) . '</td>';
                                            $html .= '</tr>';
                                        }

                                        $html .= '</tbody></table></div>';
                                    } else {
                                        $html .= '<p class="text-gray-500">No quotes submitted</p>';
                                    }

                                    $html .= '</div></details></div>';

                                    $totalQuoted = $rfqResponse->quotes->sum('total_value');
                                    $html .= '<div class="mt-4 text-right">';
                                    $html .= '<span class="text-lg font-bold">Total Quoted Amount: ₱' . number_format($totalQuoted, 2) . '</span>';
                                    $html .= '</div></div>';
                                }

                                return $html;
                            })
                            ->visible(function ($record) {
                                // Only show supplier list after bid opening AND if responses exist
                                return !is_null($record->bid_opening_datetime) && 
                                    Carbon::now()->greaterThanOrEqualTo($record->bid_opening_datetime) &&
                                    $record->rfqResponses->isNotEmpty();
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Approval Stages')
                    ->collapsible()
                    ->collapsed(false)
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
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'Approved' => 'success',
                                        'Pending'  => 'warning',
                                        'Rejected' => 'danger',
                                        default    => 'gray',
                                    }),
                                TextEntry::make('action_at')
                                    ->label('')
                                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('M d, Y') : '—')
                                    ->color(fn ($record) => $record->status === 'Rejected' ? 'danger' 
                                                        : ($record->status === 'Approved' ? 'success' : 'gray'))
                                    ->icon(fn ($record) => $record->status === 'Approved' ? 'heroicon-o-check-circle'
                                                        : ($record->status === 'Rejected' ? 'heroicon-o-x-circle' : '')),
                            ])
                            ->columns(5),
                        TextEntry::make('no_approvers')
                            ->label('')
                            ->default('No approvers assigned.')
                            ->hidden(fn ($record) => $record->approvals->count() > 0),
                    ]),
            ]);
    }

    public function getRelationManagers(): array
    {
        return [];
    }

    // Define header actions
    protected function getHeaderActions(): array
    {
        $hasRfqApproved = $this->hasRfqApproved();
        $isRejected = $this->record->status === 'Rejected';
        
        if ($isRejected) {
            return [
                $viewPdf->disabled(!$hasRfqApproved),
            ];
        }
        
        // Always show View PDF action
        $viewPdf = Action::make('viewPdf')
            ->label('View PDF')
            ->icon('heroicon-o-document-text')
            ->url(fn () => route('procurements.aoq.pdf', $this->record->parent_id), true)
            ->openUrlInNewTab()
            ->color('info')
            ->disabled(fn () => !$hasRfqApproved)
            ->tooltip(fn () => !$hasRfqApproved ? 'RFQ must be approved first' : null);
        
        // If rejected, show revise button and PDF
        if ($isRejected && $hasRfqApproved) {
            return [$viewPdf];
        }
        
        // If RFQ is not approved, only show the PDF action (disabled) and the warning modal action
        if (!$hasRfqApproved) {
            $rfqWarning = Action::make('rfqWarning')
                ->label('')
                ->modalHeading('⚠️ RFQ Approval Required')
                ->modalDescription('The Request for Quotation (RFQ) must be approved first before you can manage this Abstract of Quotation.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Go to RFQ')
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalIconColor('danger')
                ->color('danger')
                ->extraModalFooterActions([
                    Action::make('goToRfq')
                        ->label('Go to RFQ')
                        ->url(route('filament.admin.resources.procurements.view-rfq', $this->record->parent_id))
                        ->color('primary')
                        ->button(),
                ])
                ->modalCancelAction(false)
                ->closeModalByClickingAway(false)
                ->extraAttributes(['style' => 'display: none;']);
            
            return [$rfqWarning, $viewPdf];
        }
        
        // Original actions if RFQ is approved
        $isLocked = $this->record->status === 'Locked';
        $isEvaluated = $this->record->status === 'Evaluated';
        $hasUnresolvedTies = $this->hasUnresolvedTies($this->record);
        $hasTieBreakingRecord = \DB::table('aoq_tie_breaking_records')
            ->where('procurement_id', $this->record->id)
            ->exists();
        
        // Check evaluation completion
        $totalDocs = $this->record->rfqResponses->sum(function($r) {
            $docCount = is_array($r->documents) ? count($r->documents) : 0;
            return $docCount + (!empty($r->rfq_document) ? 1 : 0);
        });
        
        $evaluatedDocs = AoqEvaluation::where('procurement_id', $this->record->id)
            ->where(function($query) {
                $query->where('requirement', 'not like', 'quote_%')
                    ->orWhere('requirement', 'rfq_document');
            })
            ->count();
        
        $evaluationComplete = $totalDocs > 0 && $evaluatedDocs >= $totalDocs;
        
        // Get RFQ for checking bid opening time
        $rfq = Procurement::where('parent_id', $this->record->parent_id)
            ->where('module', 'request_for_quotation')
            ->first();
        
        $canShowCreateResponse = !$isLocked && 
            !is_null($this->record->bid_opening_datetime) && 
            Carbon::now()->greaterThanOrEqualTo($this->record->bid_opening_datetime);
        
        return [
            Action::make('setBidOpening')
                ->label('Set Bid Opening')
                ->icon('heroicon-o-calendar')
                ->color('warning')
                ->modalHeading('Set Date and Time of Bid Opening')
                ->modalDescription('Specify when the supplier bids can be opened for evaluation.')
                ->modalSubmitActionLabel('Save')
                ->form([
                    DateTimePicker::make('bid_opening_datetime')
                        ->label('Date and Time of Bid Opening')
                        ->required()
                        ->afterOrEqual(now())
                        ->timezone('Asia/Manila')
                        ->native(true)
                        ->seconds(false)
                        ->displayFormat('Y-m-d h:i A')
                        ->default(now()->setTimezone('Asia/Manila')->addMinutes(5))
                        ->helperText('Select a date and time after the current time. Evaluations can only start at or after this time.')
                        ->rule(function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                $rfq = Procurement::where('parent_id', $this->record->parent_id)
                                    ->where('module', 'request_for_quotation')
                                    ->first();
                                
                                $inputDateTime = Carbon::parse($value)->setTimezone('Asia/Manila');
                                $currentTime = now()->setTimezone('Asia/Manila');
                                
                                if ($inputDateTime->lessThanOrEqualTo($currentTime)) {
                                    $fail('The bid opening date and time must be after the current time.');
                                }
                                
                                if ($rfq && $rfq->deadline_date) {
                                    $rfqDeadline = Carbon::parse($rfq->deadline_date)->setTimezone('Asia/Manila');
                                    if ($inputDateTime->lessThan($rfqDeadline)) {
                                        $fail('The bid opening date and time must be after the RFQ deadline (' . $rfqDeadline->format('Y-m-d h:i A') . ').');
                                    }
                                }
                            };
                        }),
                ])
                ->action(function (array $data) {
                    $bidDateTime = Carbon::parse($data['bid_opening_datetime'])->setTimezone('Asia/Manila');
                    
                    $this->record->update(['bid_opening_datetime' => $bidDateTime]);
                    
                    Notification::make()
                        ->title('Bid Opening Scheduled')
                        ->body('Date and Time of Bid Opening set to ' . $bidDateTime->format('Y-m-d h:i A') . '.')
                        ->success()
                        ->send();
                })
                ->visible(function () use ($isLocked) {
                    if ($isLocked) return false;
                    return is_null($this->record->bid_opening_datetime) || 
                        Carbon::now()->setTimezone('Asia/Manila')->lessThan($this->record->bid_opening_datetime);
                }),

            Action::make('createResponse')
                ->label('Create Response')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->modalHeading('Create RFQ Response')
                ->modalSubmitActionLabel('Create Response')
                ->modalWidth('7xl')
                ->form($this->getRfqResponseFormSchema())
                ->action(function (array $data) {
                    $rfq = Procurement::where('parent_id', $this->record->parent_id)
                        ->where('module', 'request_for_quotation')
                        ->first();

                    if (!$rfq) {
                        Notification::make()
                            ->title('Error')
                            ->body('RFQ not found for this AOQ.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $supplier = Supplier::find($data['supplier_id']);
                    if ($supplier && isset($data['documents'])) {
                        $this->updateSupplierDocuments($supplier, $data['documents']);
                    }
                    if ($supplier) {
                        $supplier->update(collect($data)->only([
                            'business_name', 'business_address', 'contact_no', 'email_address',
                            'tin', 'vat', 'nvat', 'philgeps_reg_no',
                            'lbp_account_name', 'lbp_account_number'
                        ])->toArray());
                    }

                    $quotes = $data['quotes'] ?? [];
                    unset($data['quotes']);

                    $data['procurement_id'] = $rfq->id;
                    $rfqResponse = RfqResponse::create($data);

                    foreach ($quotes as $quoteData) {
                        $rfqResponse->quotes()->create([
                            'procurement_item_id' => $quoteData['procurement_item_id'],
                            'unit_value'          => $quoteData['unit_value'] ?? 0,
                            'total_value'         => $quoteData['total_value'] ?? 0,
                            'specifications'      => $quoteData['specifications'] ?? null,
                            'statement_of_compliance' => $quoteData['statement_of_compliance'] ?? true,
                        ]);
                    }

                    ActivityLogger::log(
                        'Supplier Response Created',
                        'Supplier response from ' . ($rfqResponse->supplier->business_name ?? 'Unknown Supplier') .
                        ' was submitted for RFQ ' . ($rfq->procurement_id ?? 'N/A') .
                        ' by ' . (Auth::user()->name ?? 'System')
                    );

                    Notification::make()
                        ->title('Response Created')
                        ->body('Supplier response created successfully.')
                        ->success()
                        ->send();

                        $this->dispatch('$refresh');

                    redirect()->route('filament.admin.resources.procurements.view-aoq', $this->record->parent_id);
                })
                ->visible(function () use ($canShowCreateResponse) {
                    if (!$canShowCreateResponse) return false;
                    
                    // Check if all distributed suppliers have submitted responses
                    $rfq = Procurement::where('parent_id', $this->record->parent_id)
                        ->where('module', 'request_for_quotation')
                        ->first();
                    
                    if (!$rfq) return false;
                    
                    $distributedCount = RfqDistribution::where('procurement_id', $rfq->id)->count();
                    $responseCount = RfqResponse::where('procurement_id', $rfq->id)->count();
                    
                    return $responseCount < $distributedCount;
                }),

            Action::make('evaluate')
                ->label('Evaluate Suppliers')
                ->icon('heroicon-o-check-circle')
                ->color('primary')
                ->modalHeading('Evaluate Suppliers')
                ->modalDescription('Review documents and quotes. Mark documents as Pass/Fail. Suppliers with any failed documents will be automatically disqualified from winning bids.')
                ->modalSubmitActionLabel('Save Evaluations')
                ->modalWidth('7xl')
                ->mountUsing(function () {
                    $this->loadCustomCollections();
                })
                ->form($this->getEvaluateFormSchema())
                ->fillForm($this->getEvaluateFormData())
                ->action(function (array $data, $record) {
                    if (is_null($record->bid_opening_datetime)) {
                        Notification::make()
                            ->title('Cannot Evaluate')
                            ->body('Please set the Date and Time of Bid Opening first.')
                            ->danger()
                            ->send();
                        return;
                    }
                    if (Carbon::now()->lessThan($record->bid_opening_datetime)) {
                        Notification::make()
                            ->title('Cannot Evaluate')
                            ->body('Evaluations can only start at or after ' . $record->bid_opening_datetime->format('Y-m-d h:i A') . '.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $rfq = Procurement::where('parent_id', $record->parent_id)
                        ->where('module', 'request_for_quotation')
                        ->first();
                    
                    if (!$rfq) {
                        throw new \Exception('RFQ not found for this AOQ');
                    }
                    
                    $rfqResponses = RfqResponse::where('procurement_id', $rfq->id)
                        ->with(['aoqEvaluations' => function ($query) use ($record) {
                            $query->where('procurement_id', $record->id);
                        }])
                        ->get();
                    
                    $evaluatedCount = 0;
                    
                    foreach ($rfqResponses as $rfqResponse) {
                        $documentData = $data['documents_' . $rfqResponse->id] ?? [];
                        
                        foreach ($documentData as $evalData) {
                            $requirement = $evalData['requirement'];
                            $statusValue = $evalData['status'] ? 'pass' : 'fail';
                            
                            $normalizedRequirement = $requirement;
                            if (in_array($requirement, ['rfq_document', 'original_rfq_document'])) {
                                $normalizedRequirement = 'rfq_document';
                            } else {
                                $normalizedRequirement = $this->normalizeRequirement($requirement);
                            }
                            
                            $result = AoqEvaluation::updateOrCreate(
                                [
                                    'rfq_response_id' => $rfqResponse->id,
                                    'procurement_id' => $record->id,
                                    'requirement' => $normalizedRequirement,
                                ],
                                [
                                    'status' => $statusValue,
                                    'remarks' => $evalData['remarks'] ?? null,
                                    'lowest_bid' => false,
                                ]
                            );
                            
                            $evaluatedCount++;
                        }
                    }
                    
                    $this->detectLowestBids($record);
                    $record->update(['status' => 'Evaluated']);
                    
                    Notification::make()
                        ->title('Evaluations Saved Successfully')
                        ->body("Evaluated {$evaluatedCount} documents. Lowest responsive bids automatically detected.")
                        ->success()
                        ->send();
                    
                    return redirect()->route('filament.admin.resources.procurements.view-aoq', ['record' => $record->parent_id]);
                })
                ->visible(function () use ($isLocked, $evaluationComplete) {
                    if ($isLocked) return false;
                    
                    // Check if all distributed suppliers have submitted responses
                    $rfq = Procurement::where('parent_id', $this->record->parent_id)
                        ->where('module', 'request_for_quotation')
                        ->first();
                    
                    if (!$rfq) return false;
                    
                    $distributedCount = RfqDistribution::where('procurement_id', $rfq->id)->count();
                    $responseCount = RfqResponse::where('procurement_id', $rfq->id)->count();
                    
                    // Only show if all suppliers have responded
                    if ($responseCount < $distributedCount) return false;
                    
                    return !$evaluationComplete &&
                        in_array($this->record->status, ['Pending', 'Evaluated']) &&
                        $this->record->rfqResponses->count() > 0 &&
                        !is_null($this->record->bid_opening_datetime) &&
                        Carbon::now()->greaterThanOrEqualTo($this->record->bid_opening_datetime);
                }),

            Action::make('performTieBreaking')
                ->label('Start Tie-Breaking')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('🎲 Resolve Tied Bids')
                ->modalDescription(function () {
                    $tieInfo = $this->detectTiedSuppliers($this->record);
                    if (!$tieInfo) return 'No tie detected.';
                    
                    $method = $tieInfo['method'] === 'coin_toss' ? 'coin toss' : 'random draw';
                    $suppliers = collect($tieInfo['suppliers'])->pluck('supplier_name')->join(', ', ' and ');
                    
                    return sprintf(
                        '%d suppliers are tied at ₱%s: %s. Click "Start" to perform a %s and determine the winner.',
                        $tieInfo['count'],
                        number_format($tieInfo['amount'], 2),
                        $suppliers,
                        $method
                    );
                })
                ->modalSubmitActionLabel('🎲 Start Tie-Breaking')
                ->action(function () {
                    $tieInfo = $this->detectTiedSuppliers($this->record);
                    
                    if (!$tieInfo) {
                        Notification::make()
                            ->title('No Tie Detected')
                            ->body('There are no tied bids to resolve.')
                            ->warning()
                            ->send();
                        return;
                    }
                    
                    // Perform tie-breaking and update evaluations
                    $winner = $this->performTieBreaking($tieInfo, $this->record);
                    $this->storeTieBreakingRecord($this->record, $tieInfo, $winner);
                    $this->detectLowestBids($this->record);
                    
                    Notification::make()
                        ->title('🏆 Tie-Breaking Complete!')
                        ->body(sprintf(
                            'Winner: %s (₱%s) - Determined by %s',
                            $winner['supplier_name'],
                            number_format($winner['total_quoted'], 2),
                            $tieInfo['method'] === 'coin_toss' ? 'Coin Toss' : 'Random Draw'
                        ))
                        ->success()
                        ->duration(8000)
                        ->send();
                    
                    return redirect()->route('filament.admin.resources.procurements.view-aoq', ['record' => $this->record->parent_id]);
                })
                ->visible(function () use ($isLocked, $isEvaluated, $hasUnresolvedTies, $evaluationComplete) {
                    return !$isLocked && $isEvaluated && $evaluationComplete && $hasUnresolvedTies;
                }),
            
            Action::make('viewTieBreaking')
                ->label('View Tie-Breaking Details')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('info')
                ->visible(function () use ($hasTieBreakingRecord) {
                    return $hasTieBreakingRecord;
                })
                ->modalContent(function () {
                    $tieRecord = \DB::table('aoq_tie_breaking_records')
                        ->where('procurement_id', $this->record->id)
                        ->latest()
                        ->first();
                    
                    if (!$tieRecord) {
                        return view('filament.components.empty-state', [
                            'message' => 'No tie-breaking record found.'
                        ]);
                    }

                    $tiedSuppliers = json_decode($tieRecord->tied_suppliers_data, true);

                    return view('filament.components.tie-breaking-details', [
                        'record' => $tieRecord,
                        'suppliers' => $tiedSuppliers,
                    ]);
                })
                ->modalWidth('3xl')
                ->slideOver()
                ->modalSubmitAction(false) // Remove Submit button
                ->modalCancelAction(fn ($action) => $action
                    ->label('Close')
                    ->color('primary')
                    ->icon('heroicon-o-x-mark')
                    ->button()
                ),
                
            Action::make('lock')
                ->label('Lock')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Lock AOQ')
                ->modalDescription('Once locked, this AOQ cannot be edited. Select who prepared this AOQ.')
                ->form([
                    Forms\Components\Select::make('prepared_by')
                        ->label('Prepared By')
                        ->options(function () {
                            return \App\Models\Employee::all()->mapWithKeys(function ($employee) {
                                return [$employee->id => $employee->full_name];
                            })->toArray();
                        })
                        ->searchable()
                        ->required()
                        ->helperText('Select the employee who prepared this Abstract of Quotation'),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => 'Locked',
                        'prepared_by' => $data['prepared_by'],
                    ]);
                    $this->record->refresh();

                    // Force Livewire to re-render header actions
                    $this->dispatch('$refresh');

                    ActivityLogger::log(
                        'Locked Abstract of Quotation',
                        'Abstract of Quotation ' . $this->record->procurement_id . ' was locked by ' . Auth::user()->name
                    );

                    // Send Gmail notification to all approvers
                    $approvers = $this->record->approvals()
                        ->where('module', 'abstract_of_quotation')
                        ->with('employee.user')
                        ->get();

                    foreach ($approvers as $approval) {
                        $user = $approval->employee->user ?? null;
                        if ($user && $user->email) {
                            try {
                                \Mail::to($user->email)->send(
                                    new \App\Mail\AbstractOfQuotationLockedMail($this->record)
                                );
                            } catch (\Exception $e) {
                                \Log::error("Failed to send AOQ locked email to {$user->email}: {$e->getMessage()}");
                            }
                        }
                    }

                    Notification::make()
                        ->title('AOQ Locked and approvers notified.')
                        ->success()
                        ->send();
                })
                ->visible(function () use ($isLocked, $isEvaluated, $hasUnresolvedTies, $evaluationComplete) {
                    // Only show Lock button if status is still Pending OR Evaluated (but not yet locked/rejected)
                    return in_array($this->record->status, ['Pending', 'Evaluated'])
                        && !$isLocked
                        && $isEvaluated
                        && $evaluationComplete
                        && !$hasUnresolvedTies;
                }),

                Action::make('viewPdf')
                ->label('View PDF')
                ->icon('heroicon-o-document-text')
                ->url(fn () => route('procurements.aoq.pdf', $this->record->parent_id), true)
                ->openUrlInNewTab()
                ->color('info'),
        ];
    }

    // Generate form schema for supplier evaluation
    private function getEvaluateFormSchema()
    {
        $rfq = Procurement::where('parent_id', $this->record->parent_id)
            ->where('module', 'request_for_quotation')
            ->first();
        
        if (!$rfq) {
            return [Placeholder::make('no_rfq')->content('No RFQ found for this AOQ.')];
        }
        
        $rfqResponses = RfqResponse::where('procurement_id', $rfq->id)
            ->with(['supplier:id,business_name'])
            ->get();
        
        if ($rfqResponses->isEmpty()) {
            return [Placeholder::make('no_suppliers')->content('No supplier responses to evaluate yet.')];
        }
        
        $schema = [];
        
        foreach ($rfqResponses as $rfqResponse) {
            $documentFields = [];
            $hasAnyDocuments = !empty($rfqResponse->rfq_document) || (is_array($rfqResponse->documents) && !empty($rfqResponse->documents));
            
            if ($hasAnyDocuments) {
                $documentFields[] = Repeater::make('documents_' . $rfqResponse->id)
                    ->label('Document Evaluation')
                    ->schema([
                        Forms\Components\Grid::make(12)
                            ->schema([
                                TextInput::make('requirement')
                                    ->label('Document Type')
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->formatStateUsing(fn ($state) => $state === 'rfq_document' ? 'Original RFQ Document' : ucwords(str_replace('_', ' ', $state)))
                                    ->columnSpan(3),
                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('viewDocument')
                                        ->label('View Document')
                                        ->icon('heroicon-o-eye')
                                        ->color('info')
                                        ->url(function ($get) {
                                            $path = $get('document_path');
                                            if (filled($path) && is_string($path)) {
                                                return Storage::disk('public')->exists($path) 
                                                    ? Storage::disk('public')->url($path) 
                                                    : '#';
                                            }
                                            return '#';
                                        }, true)
                                        ->openUrlInNewTab()
                                        ->disabled(function ($get) {
                                            $path = $get('document_path');
                                            return !filled($path) || !is_string($path) || !Storage::disk('public')->exists($path);
                                        })
                                ])
                                ->columnSpan(2),
                                Toggle::make('status')
                                    ->label('Pass/Fail')
                                    ->inline(false)
                                    ->onColor('success')
                                    ->offColor('danger')
                                    ->onIcon('heroicon-o-check')
                                    ->offIcon('heroicon-o-x-mark')
                                    ->default(true)
                                    ->required()
                                    ->columnSpan(2),
                                Textarea::make('remarks')
                                    ->label('Remarks')
                                    ->placeholder('Optional evaluation notes')
                                    ->rows(1)
                                    ->columnSpan(5),
                                Hidden::make('document_path')
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->defaultItems(function () use ($rfqResponse) {
                        $count = 0;
                        if (!empty($rfqResponse->rfq_document)) $count++;
                        if (is_array($rfqResponse->documents)) $count += count($rfqResponse->documents);
                        return $count;
                    })
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columnSpanFull();
            } else {
                $documentFields[] = Placeholder::make('no_documents_' . $rfqResponse->id)
                    ->content('No documents submitted by this supplier.');
            }

            $supplierName = $rfqResponse->supplier?->business_name ?? $rfqResponse->business_name ?? 'Unknown Supplier';
            $schema[] = FormSection::make($supplierName)
                ->description('Evaluate document completeness. Toggle ON = Pass, OFF = Fail. Any failed document will disqualify this supplier.')
                ->schema($documentFields)
                ->collapsible()
                ->collapsed(false);
        }
        
        return $schema;
    }

    private function getEvaluateFormData()
    {
        $rfq = Procurement::where('parent_id', $this->record->parent_id)
            ->where('module', 'request_for_quotation')
            ->first();
        
        if (!$rfq) {
            return [];
        }
        
        $rfqResponses = RfqResponse::where('procurement_id', $rfq->id)
            ->with(['aoqEvaluations' => function ($query) {
                $query->where('procurement_id', $this->record->id);
            }])
            ->get();
        
        $formData = [];
        
        foreach ($rfqResponses as $rfqResponse) {
            $allDocuments = [];
            
            if (!empty($rfqResponse->rfq_document)) {
                $rfqEval = $rfqResponse->aoqEvaluations->firstWhere('requirement', 'rfq_document') ??
                          $rfqResponse->aoqEvaluations->firstWhere('requirement', 'original_rfq_document');
                
                if (!$rfqEval) {
                    $rfqEval = AoqEvaluation::where('procurement_id', $this->record->id)
                        ->where('rfq_response_id', $rfqResponse->id)
                        ->whereIn('requirement', ['rfq_document', 'original_rfq_document'])
                        ->first();
                }
                
                $allDocuments[] = [
                    'requirement' => 'rfq_document',
                    'document_path' => $rfqResponse->rfq_document,
                    'status' => $rfqEval ? ($rfqEval->status === 'pass') : true,
                    'remarks' => $rfqEval?->remarks ?? '',
                ];
            }
            
            if (is_array($rfqResponse->documents) && !empty($rfqResponse->documents)) {
                foreach ($rfqResponse->documents as $requirement => $path) {
                    $normalizedReq = $this->normalizeRequirement($requirement);
                    $eval = $rfqResponse->aoqEvaluations->firstWhere('requirement', $normalizedReq);
                    
                    $allDocuments[] = [
                        'requirement' => $requirement,
                        'document_path' => $path,
                        'status' => $eval ? ($eval->status === 'pass') : true,
                        'remarks' => $eval?->remarks ?? '',
                    ];
                }
            }
            
            if (!empty($allDocuments)) {
                $formData['documents_' . $rfqResponse->id] = $allDocuments;
            }
        }
        
        return $formData;
    }

    public function getFooter(): ?\Illuminate\Contracts\View\View
    {
        $missing = $this->getFirstMissingRequirement();
        if ($missing) {
            return view('filament.widgets.approval-warning-modal', $missing);
        }
        return null;
    }

    // Normalize requirement names for consistency
    private function normalizeRequirement($requirement)
    {
        return strtolower(str_replace(' ', '_', $requirement));
    }
}