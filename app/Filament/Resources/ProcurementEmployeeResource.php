<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcurementEmployeeResource\Pages;
use Illuminate\Support\Facades\Auth;
use App\Models\Procurement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProcurementEmployeeResource extends Resource
{
    protected static ?string $model = Procurement::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationLabel = 'Procurement Employees';

    protected static ?string $navigationGroup = 'Procurement Management';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Procurement Employee Information')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->relationship('employee', 'full_name')
                            ->required()
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::check(); // All authenticated users
    }

    public static function canViewAny(): bool
    {
        return Auth::check(); // All users can view list
    }

    public static function canView($record): bool
    {
        return Auth::check(); // All users can view employees
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->whereNull('module');
            })
            ->columns([
                Tables\Columns\TextColumn::make('procurement_id')
                    ->label('Procurement ID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fundCluster.name')
                    ->label('Fund Cluster')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'danger' => 'Locked',
                        'success' => 'Approved',
                        'warning' => 'Pending',
                    ])
                    ->sortable(),
                    Tables\Columns\TextColumn::make('created_at')
    ->label('Created At')
    ->dateTime('M d, Y - h:i A')
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
                Tables\Actions\ViewAction::make()->url(fn ($record) => static::getUrl('view-employees', ['record' => $record])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcurementEmployees::route('/'),
            'view-employees' => Pages\ViewProcurementEmployees::route('/{record}/employees'),
        ];
    }
}