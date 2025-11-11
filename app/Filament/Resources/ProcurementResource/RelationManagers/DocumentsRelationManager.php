<?php
namespace App\Filament\Resources\ProcurementResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('module')
                    ->options([
                        'ppmp' => 'PPMP',
                        'purchase_request' => 'Purchase Request',
                        'request_for_quotation' => 'Request for Quotation',
                        'abstract_of_quotation' => 'Abstract of Quotation',
                        'bac_resolution_recommending_award' => 'BAC Resolution',
                        'purchase_order' => 'Purchase Order',
                    ])
                    ->required(),
                Forms\Components\Select::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->nullable(),
                Forms\Components\FileUpload::make('file')
                    ->disk('public')
                    ->directory(fn ($livewire) => 'Uploads/' . $livewire->ownerRecord->module)
                    ->preserveFilenames()
                    ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                    ->required()
                    ->multiple(false)
                    ->getUploadedFileNameForStorageUsing(fn ($file) => $file->getClientOriginalName()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('module')
                    ->badge(),
                Tables\Columns\TextColumn::make('file_name'),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->default('N/A'),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('file_path')
                    ->formatStateUsing(fn ($state) => '<a href="' . Storage::url($state) . '" target="_blank">View</a>')
                    ->html(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}