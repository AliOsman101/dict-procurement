<?php

namespace App\Filament\Resources\ProcurementResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Get;
use App\Models\Supplier;
use App\Models\ProcurementItem;
use App\Models\Procurement;
use App\Models\RfqDistribution;
use App\Models\RfqResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use App\Helpers\ActivityLogger;
use Illuminate\Support\Facades\Auth;


class RfqResponsesRelationManager extends RelationManager
{
    protected static string $relationship = 'rfqResponses';
    protected static ?string $title = 'Supplier Responses';
    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canShowTitle(): bool
    {
        return true;
    }

    // Retrieve the query for the RFQ responses table with related supplier and quote data
    protected function getTableQuery(): ?Builder
    {
        return $this->getRelationship()->getQuery()->with(['supplier', 'quotes.procurementItem']);
    }

    // Determine the procurement ID for fetching related items
    protected function getProcurementIdForItems(): int
    {
        // Handle both RFQ and AOQ contexts
        if ($this->ownerRecord->module === 'abstract_of_quotation') {
            $rfq = Procurement::where('parent_id', $this->ownerRecord->parent_id)
                ->where('module', 'request_for_quotation')
                ->first();
            $procurementId = $rfq ? $rfq->id : $this->ownerRecord->parent_id;
        } elseif ($this->ownerRecord->module === 'request_for_quotation') {
            $purchaseRequest = Procurement::where('parent_id', $this->ownerRecord->parent_id)
                ->where('module', 'purchase_request')
                ->first();
            $procurementId = $purchaseRequest ? $purchaseRequest->id : $this->ownerRecord->parent_id;
        } else {
            $procurementId = $this->ownerRecord->id;
        }
        return $procurementId;
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('RFQ Response Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Group::make()
                                    ->schema(function () use ($form) {
                                        $isEdit = $form->getOperation() === 'edit';
                                        if ($isEdit) {
                                            return [
                                                TextInput::make('business_name')
                                                    ->label('Supplier')
                                                    ->readOnly()
                                                    ->dehydrated(false)
                                                    ->default(function ($record) {
                                                        return $record->supplier ? $record->supplier->business_name : 'Unknown Supplier';
                                                    }),
                                                Forms\Components\Hidden::make('supplier_id')
                                                    ->required(),
                                            ];
                                        }
                                        return [
                                            Select::make('supplier_id')
                                                ->options(function () {
                                                    // Get RFQ id regardless of whether we're on RFQ or AOQ page
                                                    $rfqId = $this->ownerRecord->module === 'request_for_quotation' 
                                                        ? $this->ownerRecord->id 
                                                        : Procurement::where('parent_id', $this->ownerRecord->parent_id)
                                                            ->where('module', 'request_for_quotation')
                                                            ->value('id');
                                                    
                                                    $existingSupplierIds = RfqResponse::where('procurement_id', $rfqId)
                                                        ->pluck('supplier_id')
                                                        ->toArray();
                                                    
                                                    return RfqDistribution::where('procurement_id', $rfqId)
                                                        ->whereNotIn('supplier_id', $existingSupplierIds)
                                                        ->with('supplier')
                                                        ->get()
                                                        ->pluck('supplier.business_name', 'supplier_id')
                                                        ->toArray();
                                                })
                                                ->required()
                                                ->live()
                                                ->afterStateUpdated(function (callable $set, $state, Get $get) {
                                                    if ($state) {
                                                        $supplier = Supplier::find($state);
                                                        if ($supplier) {
                                                            // Populate supplier information
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
                                                            
                                                            // Determine required documents based on procurement
                                                            $procurement = $this->ownerRecord;
                                                            $abc = $procurement->parent->children()->where('module', 'purchase_request')->first()->grand_total ?? 0;
                                                            $categoryName = $procurement->category->name ?? '';
                                                            $requirements = $this->getRequiredDocuments($categoryName, $abc);
                                                            
                                                            // Pre-fill available supplier documents
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
                                        ];
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
                                    ->maxLength(255)
                                    ->hidden(fn () => $form->getOperation() === 'edit'),
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

                Section::make('Original RFQ Document')
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

                Section::make('Supplier Documents')
                    ->description('Documents will be pre-filled from supplier records when available. You can replace them with updated versions.')
                    ->schema($this->getDocumentUploads($form))
                    ->columns(1)
                    ->collapsible(),

                Section::make('Item Quotes')
                    ->schema([
                        Repeater::make('quotes')
                            ->relationship('quotes')
                            ->schema([
                                Forms\Components\Hidden::make('procurement_item_id')
                                    ->required(),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Group::make()
                                            ->schema([
                                                TextInput::make('item_no')
                                                    ->label(fn ($get) => $this->ownerRecord->parent->children()->where('module', 'purchase_request')->first()->basis === 'lot' ? 'Lot No.' : 'Item No.')
                                                    ->numeric()
                                                    ->readOnly(),
                                                TextInput::make('unit')
                                                    ->label('Unit')
                                                    ->readOnly(),
                                                Textarea::make('item_description')
                                                    ->label(fn ($get) => $this->ownerRecord->parent->children()->where('module', 'purchase_request')->first()->basis === 'lot' ? 'Lot Description' : 'Item Description')
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
                            ->afterStateHydrated(function ($component, $state) {
                                if (!is_array($state)) {
                                    return;
                                }

                                $procurementId = $this->getProcurementIdForItems();
                                $procurementItems = ProcurementItem::where('procurement_id', $procurementId)
                                    ->orderBy('sort')
                                    ->get()
                                    ->keyBy('id');

                                $hydratedState = [];
                                foreach ($state as $key => $item) {
                                    $procurementItemId = $item['procurement_item_id'] ?? null;
                                    $procurementItem = $procurementItemId ? $procurementItems->get($procurementItemId) : null;

                                    $hydratedState[$procurementItemId] = [
                                        'id' => $item['id'] ?? null,
                                        'procurement_item_id' => $procurementItemId,
                                        'item_no' => $item['item_no'] ?? ($procurementItem->sort ?? null),
                                        'unit' => $item['unit'] ?? ($procurementItem->unit ?? null),
                                        'quantity' => $item['quantity'] ?? ($procurementItem->quantity ?? null),
                                        'item_description' => $item['item_description'] ?? ($procurementItem->item_description ?? null),
                                        'total_cost' => $item['total_cost'] ?? ($procurementItem->total_cost ?? null),
                                        'statement_of_compliance' => $item['statement_of_compliance'] ?? true,
                                        'specifications' => $item['specifications'] ?? null,
                                        'unit_value' => $item['unit_value'] ?? null,
                                        'total_value' => $item['total_value'] ?? null,
                                    ];
                                }

                                $component->state($hydratedState);
                            })
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ]),
            ]);
    }

    // Generate schema for supplier document uploads based on procurement requirements
    protected function getDocumentUploads(Forms\Form $form): array
    {
        $procurement = $this->ownerRecord;
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
                ->helperText(
                    $requirement === 'omnibus_sworn_statement' && $abc < 50000
                        ? 'Optional for ABC below 50k'
                        : 'Required'
                )
                ->getUploadedFileNameForStorageUsing(function ($file, Get $get) use ($requirement) {
                    $supplierId = $get('supplier_id') ?? 'new';
                    $timestamp = time();
                    $extension = $file->getClientOriginalExtension();
                    return "supplier_{$supplierId}_{$requirement}_{$timestamp}.{$extension}";
                })
                ->afterStateHydrated(function ($component, $state) {
                    if (is_string($state) && !empty($state)) {
                        $component->state([$state]);
                    }
                })
                ->dehydrateStateUsing(function ($state) use ($requirement) {
                    if (is_array($state) && !empty($state)) {
                        return isset($state[0]) ? $state[0] : reset($state);
                    }
                    return is_string($state) ? $state : null;
                })
                ->mutateDehydratedStateUsing(function ($state, Get $get) use ($requirement) {
                    $supplierId = $get('supplier_id');
                    if (!$supplierId || !$state || !is_string($state)) {
                        return $state;
                    }

                    $supplier = Supplier::find($supplierId);
                    if (!$supplier) {
                        return $state;
                    }

                    $oldSupplierPath = $supplier->{$requirement};
                    $isNewUpload = false;
                    if (!$oldSupplierPath || $oldSupplierPath !== $state) {
                        $isNewUpload = true;
                    } elseif (preg_match('/_(\d{10,})\.[^.]+$/', $state, $matches)) {
                        $filenameTimestamp = (int)$matches[1];
                        if ($filenameTimestamp > (time() - 60)) {
                            $isNewUpload = true;
                        }
                    }

                    if ($isNewUpload && $oldSupplierPath && $oldSupplierPath !== $state && Storage::disk('public')->exists($oldSupplierPath)) {
                        Storage::disk('public')->delete($oldSupplierPath);
                        $supplier->update([$requirement => $state]);
                    }

                    return $state;
                })
                ->deleteUploadedFileUsing(function ($file) use ($requirement) {
                    if (is_string($file)) {
                        Storage::disk('public')->delete($file);
                    }
                })
                ->deletable(true)
                ->downloadable(true);
        }

        return $schema;
    }

    // Determine required documents based on category and ABC
    protected function getRequiredDocuments(string $categoryName, float $abc): array
    {
        $requirements = [
            'mayors_permit',
            'philgeps_certificate',
        ];

        if (str_contains(strtolower($categoryName), 'infrastructure')) {
            $requirements[] = 'pcab_license';
        }
        if (str_contains(strtolower($categoryName), 'consulting')) {
            $requirements[] = 'professional_license_cv';
        }
        if (str_contains(strtolower($categoryName), 'catering') || str_contains(strtolower($categoryName), 'printing')) {
            $requirements[] = 'terms_conditions_tech_specs';
        }
        if ($abc > 500000) {
            $requirements[] = 'tax_return';
        }
        $requirements[] = 'omnibus_sworn_statement';

        return $requirements;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->recordUrl(null)
            ->recordAction(null)
            ->columns([
                Tables\Columns\TextColumn::make('supplier.business_name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable()
                    ->default('Unknown Supplier'),
                    
                Tables\Columns\TextColumn::make('documents')
                    ->label('Supplier Documents')
                    ->formatStateUsing(function ($state, $record) {
                        $documents = [];
                        if (is_string($state) && strpos($state, ',') !== false) {
                            $paths = array_map('trim', explode(',', $state));
                            foreach ($paths as $index => $path) {
                                if (!empty($path)) {
                                    $documents["doc_" . ($index + 1)] = trim($path);
                                }
                            }
                        } elseif (is_string($state)) {
                            $decoded = json_decode($state, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $documents = $decoded;
                            }
                        } elseif (is_array($state)) {
                            $documents = $state;
                        }
                        
                        if (empty($documents)) {
                            return '<span class="text-gray-500 text-sm">No files uploaded</span>';
                        }
                        
                        $links = [];
                        $disk = Storage::disk('public');
                        foreach ($documents as $requirement => $path) {
                            if (!empty($path) && is_string($path)) {
                                $fullPath = $path;
                                $exists = $disk->exists($fullPath);
                                $url = $exists ? $disk->url($fullPath) : null;
                                $filename = basename($path);
                                
                                if ($exists && $url) {
                                    $links[] = '<div class="mb-1"><a href="' . e($url) . '" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium inline-flex items-center gap-1 group no-underline hover:underline">' . 
                                        e($filename) . 
                                        ' <span class="text-blue-500 group-hover:text-blue-600">📄</span>' .
                                        '</a></div>';
                                } else {
                                    $links[] = '<div class="mb-1"><span class="text-gray-500 text-sm">' . e($filename) . '</span></div>';
                                }
                            }
                        }
                        
                        return empty($links) ? '<span class="text-gray-500 text-sm">No files uploaded</span>' : '<div class="space-y-1">' . implode('', $links) . '</div>';
                    })
                    ->html()
                    ->wrap(),
                    
                Tables\Columns\TextColumn::make('rfq_document')
                    ->label('RFQ Document')
                    ->formatStateUsing(function ($state, $record) {
                        if (empty($state) || !is_string($state)) {
                            return '<span class="text-gray-500 text-sm">Not uploaded</span>';
                        }
                        
                        $fullPath = $state;
                        if (!str_starts_with($state, 'rfq-original-documents/')) {
                            $fullPath = 'rfq-original-documents/' . $state;
                        }
                        
                        $disk = Storage::disk('public');
                        $exists = $disk->exists($fullPath);
                        $url = $exists ? $disk->url($fullPath) : null;
                        
                        if ($exists && $url) {
                            $filename = basename($state);
                            return '<a href="' . e($url) . '" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-semibold inline-flex items-center gap-1 group no-underline hover:underline">' . 
                                e($filename) . 
                                ' <span class="text-blue-500 group-hover:text-blue-600">📄</span>' .
                                '</a>';
                        } else {
                            return '<span class="text-red-600 text-sm">File not found</span>';
                        }
                    })
                    ->html()
                    ->weight('medium'),
                    
                Tables\Columns\TextColumn::make('submitted_by')
                    ->label('Submitted By')
                    ->searchable()
                    ->default('Not Submitted')
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('submitted_date')
                    ->label('Submitted Date')
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('supplier.email_address')
                    ->label('Email Address')
                    ->searchable()
                    ->default('Not Provided')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\Action::make('view_response_pdf')
                    ->label('View Response PDF')
                    ->url(fn ($record): string => route('procurements.rfq-response.pdf', $record->id))
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->icon('heroicon-o-document-text'),
                    
                Tables\Actions\EditAction::make()
                    ->label('Edit Response')
                    ->modalHeading('Edit RFQ Response')
                    ->modalSubmitActionLabel('Update Response')
                    ->color('primary')
                    ->icon('heroicon-o-pencil')
                    ->modalWidth('7xl')
                    ->fillForm(function ($record) {
                        $data = $record->toArray();
                        $supplier = $record->supplier;
                        
                        if ($supplier) {
                            $data['business_name'] = $data['business_name'] ?? $supplier->business_name;
                            $data['business_address'] = $data['business_address'] ?? $supplier->business_address;
                            $data['contact_no'] = $data['contact_no'] ?? $supplier->contact_no;
                            $data['email_address'] = $data['email_address'] ?? $supplier->email_address;
                            $data['tin'] = $data['tin'] ?? $supplier->tin;
                            $data['vat'] = $data['vat'] ?? $supplier->vat;
                            $data['nvat'] = $data['nvat'] ?? $supplier->nvat;
                            $data['philgeps_reg_no'] = $data['philgeps_reg_no'] ?? $supplier->philgeps_reg_no;
                            $data['lbp_account_name'] = $data['lbp_account_name'] ?? $supplier->lbp_account_name;
                            $data['lbp_account_number'] = $data['lbp_account_number'] ?? $supplier->lbp_account_number;
                            $data['designation'] = $data['designation'] ?? null;
                        }

                        // Convert document paths to arrays for FileUpload
                        if (isset($data['documents'])) {
                            if (is_string($data['documents'])) {
                                $decoded = json_decode($data['documents'], true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                    $data['documents'] = $decoded;
                                }
                            }
                            if (is_array($data['documents'])) {
                                foreach ($data['documents'] as $key => $value) {
                                    if (is_string($value)) {
                                        $data['documents'][$key] = [$value];
                                    }
                                }
                            }
                        }

                        $data['rfq_document'] = $record->rfq_document;

                        // Load quotes for the form
                        $itemsProcurementId = $this->getProcurementIdForItems();
                        $procurementItems = ProcurementItem::where('procurement_id', $itemsProcurementId)
                            ->orderBy('sort')
                            ->get();

                        $data['quotes'] = $procurementItems->map(function ($item) use ($record) {
                            $quote = $record->quotes->firstWhere('procurement_item_id', $item->id);
                            return [
                                'id' => $quote ? $quote->id : null,
                                'procurement_item_id' => $item->id,
                                'item_no' => $item->sort ?? null,
                                'unit' => $item->unit ?? null,
                                'quantity' => $item->quantity ?? null,
                                'item_description' => $item->item_description ?? null,
                                'total_cost' => $item->total_cost ?? null,
                                'statement_of_compliance' => $quote ? $quote->statement_of_compliance : true,
                                'specifications' => $quote ? $quote->specifications : null,
                                'unit_value' => $quote ? $quote->unit_value : null,
                                'total_value' => $quote ? $quote->total_value : null,
                            ];
                        })->toArray();

                        return $data;
                    })
                    ->before(function ($data, $record) {
                        if (isset($data['supplier_id'])) {
                            $supplier = Supplier::find($data['supplier_id']);
                            if ($supplier) {
                                $supplier->update([
                                    'business_name' => $data['business_name'] ?? $supplier->business_name,
                                    'business_address' => $data['business_address'] ?? $supplier->business_address,
                                    'contact_no' => $data['contact_no'] ?? $supplier->contact_no,
                                    'email_address' => $data['email_address'] ?? $supplier->email_address,
                                    'tin' => $data['tin'] ?? $supplier->tin,
                                    'vat' => $data['vat'] ?? $supplier->vat,
                                    'nvat' => $data['nvat'] ?? $supplier->nvat,
                                    'philgeps_reg_no' => $data['philgeps_reg_no'] ?? $supplier->philgeps_reg_no,
                                    'lbp_account_name' => $data['lbp_account_name'] ?? $supplier->lbp_account_name,
                                    'lbp_account_number' => $data['lbp_account_number'] ?? $supplier->lbp_account_number,
                                ]);
                            }
                        }
                    })
                    ->successNotificationTitle('Response updated successfully'),
                Tables\Actions\DeleteAction::make()->label('Delete Response')
                    ->successNotificationTitle('Response deleted successfully'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Create Response')
                    ->modalWidth('7xl')
                    ->mutateFormDataUsing(function (array $data): array {
                        return $data;
                    })
                    ->before(function ($data) {
                        if (isset($data['supplier_id'])) {
                            $existingResponse = RfqResponse::where('procurement_id', $this->ownerRecord->id)
                                ->where('supplier_id', $data['supplier_id'])
                                ->exists();
                            if ($existingResponse) {
                                Notification::make()
                                    ->title('Existing RFQ Response found, redirecting to edit')
                                    ->warning()
                                    ->send();
                                $this->redirect($this->getResource()::getUrl('edit', ['record' => RfqResponse::where('procurement_id', $this->ownerRecord->id)->where('supplier_id', $data['supplier_id'])->first()]));
                                return;
                            }
                            
                            // Update supplier information if provided
                            $supplier = Supplier::find($data['supplier_id']);
                            if ($supplier) {
                                $supplier->update([
                                    'business_name' => $data['business_name'] ?? $supplier->business_name,
                                    'business_address' => $data['business_address'] ?? $supplier->business_address,
                                    'contact_no' => $data['contact_no'] ?? $supplier->contact_no,
                                    'email_address' => $data['email_address'] ?? $supplier->email_address,
                                    'tin' => $data['tin'] ?? $supplier->tin,
                                    'vat' => $data['vat'] ?? $supplier->vat,
                                    'nvat' => $data['nvat'] ?? $supplier->nvat,
                                    'philgeps_reg_no' => $data['philgeps_reg_no'] ?? $supplier->philgeps_reg_no,
                                    'lbp_account_name' => $data['lbp_account_name'] ?? $supplier->lbp_account_name,
                                    'lbp_account_number' => $data['lbp_account_number'] ?? $supplier->lbp_account_number,
                                ]);
                            }
                        }
                    })
                    ->after(function ($record) {
                        $this->dispatch('$refresh');

                     // Log to History Logs
    ActivityLogger::log(
        'Supplier Response Created',
        'Supplier response from ' . ($record->supplier->business_name ?? 'Unknown Supplier') .
        ' was submitted for RFQ ' . ($record->procurement->procurement_id ?? 'N/A') .
        ' by ' . (Auth::user()->name ?? 'System')
    );
})

                    ->successNotificationTitle('Response created successfully'),
            ]);
    }
}