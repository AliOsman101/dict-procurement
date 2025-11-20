<?php

namespace App\Filament\Resources\Setup;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\Setup\Holiday;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\Setup\HolidayResource\Pages;

class HolidayResource extends Resource
{
    protected static ?string $model = Holiday::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->options([
                        'legal' => 'Legal Holiday',
                        'special' => 'Special Holiday',

                    ])
                    ->default('legal')
                    ->required(),
                Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('holiday_date')
                    ->required(),
                //  Forms\Components\TextInput::make('time_start'),
                //  Forms\Components\TextInput::make('time_end'),
                Forms\Components\Checkbox::make('is_whole_day'),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->formatStateUsing(fn($state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
                Tables\Columns\TextColumn::make('holiday_date')
                    ->date()
                    ->sortable(),
                //    Tables\Columns\TextColumn::make('time_start'),
                //    Tables\Columns\TextColumn::make('time_end'),
                Tables\Columns\CheckboxColumn::make('is_whole_day')
                    ->default(true)

                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHolidays::route('/'),
            'create' => Pages\CreateHoliday::route('/create'),
            'edit' => Pages\EditHoliday::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        // Show navigation if the user is an admin or has an associated employee
        return $user?->hasAnyRole(['hr-admin', 'admin', 'leave-admin', 'dtr-admin']);
    }
}
