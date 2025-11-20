<?php

namespace App\Filament\Resources\ReviewProcurementResource\Pages;

use App\Filament\Resources\ReviewProcurementResource;
use App\Models\Procurement;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use App\Models\RfqSupplier; // 👈 (create later if not existing)
use App\Models\Supplier;
use Filament\Forms;

class ApproverRfqDistribution extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ReviewProcurementResource::class;
    protected static string $view = 'filament.resources.review-procurement-resource.pages.approver-rfq-distribution';

    public Procurement $record;

    public function mount($record): void
    {
        $this->record = Procurement::findOrFail($record);
    }

    public function getTitle(): string
    {
        return 'RFQ Distribution — ' . ($this->record->procurement_id ?? 'N/A');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                RfqSupplier::query()->where('procurement_id', $this->record->id)
            )
            ->columns([
                Tables\Columns\TextColumn::make('supplier.name')->label('Supplier'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->label('Added On'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Supplier')
                    ->model(RfqSupplier::class)
                    ->form([
                        Forms\Components\Hidden::make('procurement_id')->default(fn () => $this->record->id),
                        Forms\Components\Select::make('supplier_id')
                            ->label('Supplier')
                            ->options(Supplier::pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ]),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
