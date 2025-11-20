<?php

namespace App\Filament\Resources\ProcurementResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\ProcurementItem;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('unit')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('item_description')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                Forms\Components\TextInput::make('unit_cost')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                Forms\Components\TextInput::make('sort')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->default(function () {
                        // Get the next sort value for the procurement
                        $procurementId = $this->ownerRecord->id;
                        $maxSort = ProcurementItem::where('procurement_id', $procurementId)
                            ->max('sort');
                        return $maxSort ? $maxSort + 1 : 1;
                    })
                    ->rules([
                        'required',
                        'numeric',
                        'min:1',
                        function ($attribute, $value, $fail) {
                            $procurementId = $this->ownerRecord->id;
                            $existingSort = ProcurementItem::where('procurement_id', $procurementId)
                                ->where('sort', $value)
                                ->where('id', '!=', $this->getRecord()->id ?? 0)
                                ->exists();
                            if ($existingSort) {
                                $fail('The sort value must be unique within the procurement.');
                            }
                        },
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort')
                    ->label('Item No.')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit'),
                Tables\Columns\TextColumn::make('item_description'),
                Tables\Columns\TextColumn::make('quantity'),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->money('PHP'),
                Tables\Columns\TextColumn::make('total_cost')
                    ->money('PHP'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->before(function ($data) {
                        \Log::info('CreateAction before save', [
                            'procurement_id' => $this->ownerRecord->id,
                            'item_data' => $data,
                        ]);
                    })
                    ->after(function ($record) {
                        \Log::info('CreateAction after save', [
                            'procurement_id' => $this->ownerRecord->id,
                            'item_id' => $record->id,
                            'sort' => $record->sort,
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->before(function ($data, $record) {
                        \Log::info('EditAction before save', [
                            'procurement_id' => $this->ownerRecord->id,
                            'item_id' => $record->id,
                            'item_data' => $data,
                        ]);
                    })
                    ->after(function ($record) {
                        \Log::info('EditAction after save', [
                            'procurement_id' => $this->ownerRecord->id,
                            'item_id' => $record->id,
                            'sort' => $record->sort,
                        ]);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->reorderable('sort');
    }
}