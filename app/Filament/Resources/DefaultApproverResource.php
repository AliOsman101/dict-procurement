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
                    ->reactive()
                  
                    ->rules([
                        fn ($get, $operation): \Closure => function (string $attribute, $value, \Closure $fail) use ($get, $operation) {
                            $module = $get('module');
                            if (!$module || !$value) {
                                return;
                            }

                            $query = DefaultApprover::where('employee_id', $value)
                                ->where('module', $module);

                            // Only exclude current record when editing
                            if ($operation === 'edit' && $get('../../record.id')) {
                                $query->where('id', '!=', $get('../../record.id'));
                            }

                            $exists = $query->exists();

                            if ($exists) {
                                $employee = Employee::find($value);
                                $name = $employee?->full_name ?? 'This employee';

                                $moduleNames = [
                                    'purchase_request' => 'Purchase Request',
                                    'request_for_quotation' => 'Request for Quotation',
                                    'abstract_of_quotation' => 'Abstract of Quotation',
                                    'bac_resolution_recommending_award' => 'BAC Resolution',
                                    'purchase_order' => 'Purchase Order',
                                ];

                                $moduleName = $moduleNames[$module] ?? ucwords(str_replace('_', ' ', $module));

                                $fail("{$name} is already assigned as approver for {$moduleName}.");
                            }
                        },
                    ]),

                // Removed PPMP from module options
                Forms\Components\Select::make('module')
                    ->options([
                        'purchase_request' => 'Purchase Request',
                        'request_for_quotation' => 'Request for Quotation',
                        'abstract_of_quotation' => 'Abstract of Quotation',
                        'bac_resolution_recommending_award' => 'BAC Resolution',
                        'purchase_order' => 'Purchase Order',
                    ])
                    ->required()
                    ->reactive()
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
                    ->options([
                        'DICT CAR - Admin and Finance Division' => 'Admin and Finance Division',
                        'DICT CAR - Technical Operations Division' => 'Technical Operations Division',
                    ])
                    ->visible(fn ($get) => $get('module') === 'request_for_quotation')
                    ->reactive()
                    ->required(fn ($get) => $get('module') === 'request_for_quotation')
                    ->validationMessages([
                        'required' => 'The office section field is required when module is Request for Quotation.',
                    ])
                    ->unique(
                        table: DefaultApprover::class,
                        column: 'office_section',
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule, $get) => $get('module') === 'request_for_quotation'
                            ? $rule->where('module', 'request_for_quotation')
                            : $rule->whereNull('module')
                    ),

                Forms\Components\TextInput::make('sequence')
                    ->label('Approval Sequence')
                    ->numeric()
                    ->required(fn ($get) => $get('module') !== 'request_for_quotation')
                    ->minValue(1)
                    ->default(fn ($get) => $get('module') === 'request_for_quotation' ? 1 : null)
                    ->readOnly(fn ($get) => $get('module') === 'request_for_quotation'),

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
                // Removed PPMP from filter options
                Tables\Filters\SelectFilter::make('module')
                    ->options([
                        'purchase_request' => 'Purchase Request',
                        'request_for_quotation' => 'Request for Quotation',
                        'abstract_of_quotation' => 'Abstract of Quotation',
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
