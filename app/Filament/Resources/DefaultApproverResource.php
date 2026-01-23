<?php
namespace App\Filament\Resources;

use App\Filament\Resources\DefaultApproverResource\Pages;
use App\Models\DefaultApprover;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Filament\Forms\Set;
use Filament\Forms\Get;

class DefaultApproverResource extends Resource
{
    protected static ?string $model = DefaultApprover::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Procurement Management';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->label('Employee')
                    ->relationship(
                        name: 'employee',
                        titleAttribute: 'id',
                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('lastname')->orderBy('firstname')
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Employee::where('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%")
                        ->orWhere('middlename', 'like', "%{$search}%")
                        ->orderBy('lastname')
                        ->orderBy('firstname')
                        ->pluck('full_name', 'id')
                        ->toArray())
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        // Reset module when employee changes
                        $set('module', null);
                        $set('sequence', null);
                        $set('office_section', null);
                    }),

                // Updated module select to dynamically filter out modules based on approver limits
                    Forms\Components\Select::make('module')
                    ->options(function ($record, Get $get) {
                        $allModules = [
                            'purchase_request' => 'Purchase Request',
                            'request_for_quotation' => 'Request for Quotation',
                            'abstract_of_quotation' => 'Abstract of Quotation',
                            'minutes_of_opening' => 'Minutes of Opening',
                            'bac_resolution_recommending_award' => 'BAC Resolution',
                            'purchase_order' => 'Purchase Order',
                        ];
                        
                        // Get selected employee
                        $employeeId = $get('employee_id');
                        
                        // If editing an existing record, return all modules
                        if ($record) {
                            return $allModules;
                        }
                        
                        // Define max approvers per module
                        $moduleApproverLimits = [
                            'purchase_request' => 2,
                            'request_for_quotation' => 2,
                            'abstract_of_quotation' => 5,
                            'minutes_of_opening' => 1,
                            'bac_resolution_recommending_award' => 1,
                            'purchase_order' => 2,
                        ];
                        
                        // Get current count of approvers per module
                        $approverCounts = DefaultApprover::select('module')
                            ->selectRaw('COUNT(*) as count')
                            ->groupBy('module')
                            ->pluck('count', 'module')
                            ->toArray();
                        
                        // If employee is selected, get modules they're already assigned to
                        $employeeAssignedModules = [];
                        if ($employeeId) {
                            $employeeAssignedModules = DefaultApprover::where('employee_id', $employeeId)
                                ->pluck('module')
                                ->toArray();
                        }
                        
                        // Filter out modules
                        return array_filter($allModules, function($key) use ($moduleApproverLimits, $approverCounts, $employeeAssignedModules) {
                            // Hide if employee is already assigned to this module
                            if (in_array($key, $employeeAssignedModules)) {
                                return false;
                            }
                            
                            // Hide if module has reached its limit
                            $limit = $moduleApproverLimits[$key] ?? 2;
                            $currentCount = $approverCounts[$key] ?? 0;
                            return $currentCount < $limit;
                        }, ARRAY_FILTER_USE_KEY);
                    })
                    ->required()
                    ->live()
                    ->helperText(fn (Get $get) => $get('employee_id') 
                        ? 'Only modules where this employee is not yet assigned are shown.' 
                        : 'Please select an employee first.')
                    ->afterStateUpdated(function (callable $set, $state) {
                        if ($state === 'request_for_quotation') {
                            $set('sequence', 1);
                            $set('designation', null);
                            $set('office_section', null);
                        } else {
                            $set('sequence', null);
                            $set('office_section', null);
                            $set('designation', null);
                        }
                    }),

                Forms\Components\Select::make('office_section')
                    ->options(function ($record) {
                        $allSections = [
                            'DICT CAR - Admin and Finance Division' => 'Admin and Finance Division',
                            'DICT CAR - Technical Operations Division' => 'Technical Operations Division',
                        ];
                        
                        // If editing, return all sections
                        if ($record) {
                            return $allSections;
                        }
                        
                        // Check which sections already have approvers for RFQ module
                        $assignedSections = DefaultApprover::where('module', 'request_for_quotation')
                            ->pluck('office_section')
                            ->toArray();
                        
                        // Filter out sections that already have an approver
                        return array_filter($allSections, function($key) use ($assignedSections) {
                            return !in_array($key, $assignedSections);
                        }, ARRAY_FILTER_USE_KEY);
                    })
                    ->visible(fn ($get) => $get('module') === 'request_for_quotation')
                    ->live()
                    ->required(fn ($get) => $get('module') === 'request_for_quotation')
                    ->validationMessages([
                        'required' => 'The office section field is required when module is Request for Quotation.',
                    ]),

                Forms\Components\TextInput::make('sequence')
                    ->label('Approval Sequence')
                    ->numeric()
                    ->required(fn ($get) => $get('module') !== 'request_for_quotation')
                    ->minValue(1)
                    ->default(fn ($get) => $get('module') === 'request_for_quotation' ? 1 : null)
                    ->readOnly(fn ($get) => $get('module') === 'request_for_quotation')
                    ->live(debounce: 300)
                    ->helperText(function (Get $get, $state, $component) {
                        $module = $get('module');
                        
                        // Skip if no module or sequence
                        if (!$module || !$state) {
                            return null;
                        }

                        // Allow duplicates for AOQ
                        if ($module === 'abstract_of_quotation') {
                            return 'Duplicate sequences are allowed for Abstract of Quotation.';
                        }

                        // Check for duplicate sequence
                        $query = DefaultApprover::where('module', $module)
                            ->where('sequence', $state);

                        // Exclude current record when editing
                        if ($get('../../record.id')) {
                            $query->where('id', '!=', $get('../../record.id'));
                        }

                        $exists = $query->exists();

                        if ($exists) {
                            $moduleNames = [
                                'purchase_request' => 'Purchase Request',
                                'request_for_quotation' => 'Request for Quotation',
                                'minutes_of_opening' => 'Minutes of Opening',
                                'bac_resolution_recommending_award' => 'BAC Resolution',
                                'purchase_order' => 'Purchase Order',
                            ];

                            $moduleName = $moduleNames[$module] ?? ucwords(str_replace('_', ' ', $module));
                            
                            // Return error message with inline styling for red color
                            return new \Illuminate\Support\HtmlString(
                                '<span style="color: #ef4444;">Sequence ' . $state . ' is already assigned to another approver in ' . $moduleName . '.</span>'
                            );
                        }

                        return 'Sequence is available.';
                    })
                    ->validationAttribute('sequence')
                    ->rules([
                        fn ($get, $operation): \Closure => function (string $attribute, $value, \Closure $fail) use ($get, $operation) {
                            $module = $get('module');
                            
                            // Skip validation if no module or sequence
                            if (!$module || !$value) {
                                return;
                            }

                            // Allow duplicate sequences ONLY for abstract_of_quotation
                            if ($module === 'abstract_of_quotation') {
                                return;
                            }

                            // For all other modules, check for duplicate sequences
                            $query = DefaultApprover::where('module', $module)
                                ->where('sequence', $value);

                            // Exclude current record when editing
                            if ($operation === 'edit' && $get('../../record.id')) {
                                $query->where('id', '!=', $get('../../record.id'));
                            }

                            $exists = $query->exists();

                            if ($exists) {
                                $moduleNames = [
                                    'purchase_request' => 'Purchase Request',
                                    'request_for_quotation' => 'Request for Quotation',
                                    'minutes_of_opening' => 'Minutes of Opening',
                                    'bac_resolution_recommending_award' => 'BAC Resolution',
                                    'purchase_order' => 'Purchase Order',
                                ];

                                $moduleName = $moduleNames[$module] ?? ucwords(str_replace('_', ' ', $module));
                                $fail("Please choose a different sequence number.");
                            }
                        },
                    ]),

                Forms\Components\TextInput::make('designation')
                    ->label('Designation')
                    ->required()
                    ->placeholder('e.g., BAC Member, Regional Director')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->sortable(query: fn (Builder $query) => $query->orderBy('lastname')->orderBy('firstname'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('module')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'purchase_request' => 'Purchase Request',
                        'request_for_quotation' => 'Request for Quotation',
                        'abstract_of_quotation' => 'Abstract of Quotation',
                        'minutes_of_opening' => 'Minutes of Opening',
                        'bac_resolution_recommending_award' => 'BAC Resolution',
                        'purchase_order' => 'Purchase Order',
                        default => ucwords(str_replace('_', ' ', $state)),
                    })
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('designation')
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->module === 'request_for_quotation' && $record->office_section) {
                            $section = str_replace('DICT CAR - ', '', $record->office_section);
                            $abbr = $section === 'Admin and Finance Division' ? 'AFD' : 'TOD';
                            return "{$state} ({$abbr})";
                        }
                        return $state;
                    })
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('sequence')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('module')
                    ->options([
                        'purchase_request' => 'Purchase Request',
                        'request_for_quotation' => 'Request for Quotation',
                        'abstract_of_quotation' => 'Abstract of Quotation',
                        'minutes_of_opening' => 'Minutes of Opening',
                        'bac_resolution_recommending_award' => 'BAC Resolution',
                        'purchase_order' => 'Purchase Order',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDefaultApprovers::route('/'),
            'create' => Pages\CreateDefaultApprover::route('/create'),
            'edit' => Pages\EditDefaultApprover::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->hasRole('admin');
    }
}