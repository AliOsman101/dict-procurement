<?php
namespace App\Filament\Resources;

use App\Filament\Resources\ProcurementResource\Pages;
use App\Models\FundCluster;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Procurement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

class ProcurementResource extends Resource
{
    protected static ?string $model = Procurement::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Procurements';
    protected static ?string $navigationGroup = 'Procurement Management';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Procurement Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(6),
                        Forms\Components\Select::make('fund_cluster_id')
                            ->relationship('fundCluster', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Select the fund cluster for this procurement')
                            ->columnSpan(6),
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Select the category for this procurement')
                            ->columnSpan(6),
                        Forms\Components\Select::make('procurement_type')
                            ->options([
                                'small_value_procurement' => 'Small Value Procurement',
                                'public_bidding' => 'Public Bidding (Not Yet Supported)',
                            ])
                            ->default('small_value_procurement')
                            ->required()
                            ->helperText('Select the type of procurement')
                            ->columnSpan(6)
                            ->disableOptionWhen(fn (string $value): bool => $value === 'public_bidding')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state === 'public_bidding') {
                                    Notification::make()
                                        ->warning()
                                        ->title('Public Bidding Not Supported')
                                        ->body('Public Bidding is not yet supported. Please select Small Value Procurement.')
                                        ->persistent()
                                        ->send();
                                    
                                    // Reset to default value
                                    $set('procurement_type', 'small_value_procurement');
                                }
                            }),
                    ])
                    ->columns(12)
                    ->collapsible(),
                Forms\Components\Section::make('Office/Section')
                    ->schema([
                        Forms\Components\Select::make('office_section')
                            ->options([
                                'DICT CAR - Admin and Finance Division' => 'DICT CAR - Admin and Finance Division',
                                'DICT CAR - Technical Operations Division' => 'DICT CAR - Technical Operations Division',
                            ])
                            ->required()
                            ->helperText('Select the office or section related to this procurement')
                            ->columnSpan(6),
                    ])
                    ->columns(12)
                    ->collapsible(),
                Forms\Components\Section::make('Employees')
                    ->schema([
                        Forms\Components\Select::make('employees')
                            ->multiple()
                            ->relationship(
                                'employees',
                                'id',
                                fn (Builder $query) => $query->orderByRaw('CONCAT(firstname, " ", COALESCE(middlename, ""), " ", lastname)')
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Employee::where('firstname', 'like', "%{$search}%")
                                ->orWhere('lastname', 'like', "%{$search}%")
                                ->orWhere('middlename', 'like', "%{$search}%")
                                ->orderByRaw('CONCAT(firstname, " ", COALESCE(middlename, ""), " ", lastname)')
                                ->pluck('full_name', 'id')
                                ->toArray())
                            ->preload()
                            ->required()
                            ->helperText('Select one or more employees associated with this procurement')
                            ->columnSpan(12),
                    ])
                    ->columns(12)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Show only top-level procurements (no module-specific records)
                return $query->whereNull('module');
            })
            ->columns([
                Tables\Columns\TextColumn::make('procurement_id')
                    ->label('Procurement ID')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('fundCluster.name')
                    ->label('Fund Cluster')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('requested_by')
                    ->label('Requested By')
                    ->sortable(query: fn (Builder $query) => $query->leftJoin('procurements as pr', 'procurements.id', '=', 'pr.parent_id')
                        ->where('pr.module', 'purchase_request')
                        ->orderBy('pr.requested_by'))
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        // Use PR's requested_by if set; otherwise, return 'Not set'
                        $pr = $record->children()->where('module', 'purchase_request')->first();
                        return $pr && $pr->requester ? $pr->requester->full_name : 'Not set';
                    }),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'danger' => 'Locked',
                        'success' => 'Approved',
                        'warning' => 'Pending',
                    ])
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Pending' => 'Pending',
                        'Approved' => 'Approved',
                        'Locked' => 'Locked',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('View'),
                Tables\Actions\EditAction::make()
                    ->authorize(fn (Procurement $record): bool => auth()->user()->can('update', $record)),
            ])
            
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Check if this user is a Default Approver
        $isApprover = \App\Models\DefaultApprover::whereHas('employee', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->exists();

        // ❌ Hide "Procurements" tab if the user is a Default Approver
        return ! $isApprover;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcurements::route('/'),
            'create' => Pages\CreateProcurement::route('/create'),
            'view' => Pages\ProcurementView::route('/{record}'),
            'edit' => Pages\EditProcurement::route('/{record}/edit'),
            'view-ppmp' => Pages\ViewPpmp::route('/{record}/ppmp'),
            'view-pr' => Pages\ViewPr::route('/{record}/pr'),
            'view-rfq' => Pages\ViewRfq::route('/{record}/rfq'),
            'view-aoq' => Pages\ViewAoq::route('/{record}/aoq'),
            'view-bac' => Pages\ViewBac::route('/{record}/bac'),
            'view-po' => Pages\ViewPo::route('/{record}/po'),
        ];
    }
}