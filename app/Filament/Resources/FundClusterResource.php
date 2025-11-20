<?php
namespace App\Filament\Resources;

use App\Filament\Resources\FundClusterResource\Pages;
use App\Models\FundCluster;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;


class FundClusterResource extends Resource
{
    protected static ?string $model = FundCluster::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Fund Clusters';
    protected static ?string $navigationGroup = 'Procurement Management';
    protected static ?int $navigationSort = 6;

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->hasRole('admin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Fund Cluster Name')
                    ->required()
                    ->maxLength(255)
                    ->unique(FundCluster::class, 'name', ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Fund Cluster Name')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([])
            ->actions([
    Tables\Actions\EditAction::make(),
    Tables\Actions\DeleteAction::make()
        ->after(function ($record) {
            \App\Helpers\ActivityLogger::log(
                'Deleted Fund Cluster',
                'Fund Cluster "' . $record->name . '" was deleted.'
            );
        }),
])
           ->bulkActions([
    Tables\Actions\DeleteBulkAction::make()
        ->after(function ($records) {
            foreach ($records as $record) {
                \App\Helpers\ActivityLogger::log(
                    'Deleted Fund Cluster (Bulk)',
                    'Fund Cluster "' . $record->name . '" was deleted via bulk action.'
                );
            }
        }),
    ]);
 }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFundClusters::route('/'),
            'create' => Pages\CreateFundCluster::route('/create'),
            'edit' => Pages\EditFundCluster::route('/{record}/edit'),
        ];
    }
}