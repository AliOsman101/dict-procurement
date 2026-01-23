<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HistoryLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HistoryLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;
    protected static ?string $navigationGroup = 'Procurement Management';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document';
    protected static ?string $navigationLabel = 'History Log';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('action')
                    ->searchable(),

                Tables\Columns\TextColumn::make('details')
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')

            ->headerActions([]) // Removed Clear Logs

            ->filters([

                // LOGIN / LOGOUT (only works if you implement login logs)
                Tables\Filters\SelectFilter::make('auth_filter')
                    ->label('Login / Logout')
                    ->options([
                        'Login' => 'Login',
                        'Logout' => 'Logout',
                    ])
                    ->query(function ($query, array $data) {
                        if (! $data['value']) return $query;
                        return $query->where('action', $data['value']);
                    }),

                // PURCHASE REQUEST FILTER
                Tables\Filters\SelectFilter::make('pr_filter')
                    ->label('Purchase Request Actions')
                    ->options([
                        'Locked Purchase Request'   => 'Locked Purchase Request',
                        'Approved Purchase Request' => 'Approved Purchase Request',
                        'Rejected Purchase Request' => 'Rejected Purchase Request',
                    ])
                    ->query(fn ($query, array $data) =>
                        $data['value'] ? $query->where('action', $data['value']) : $query
                    ),

                // RFQ FILTER
                Tables\Filters\SelectFilter::make('rfq_filter')
                    ->label('Request for Quotation Actions')
                    ->options([
                        'Locked Request for Quotation' => 'Locked Request for Quotation',
                        'Distributed RFQ'              => 'Distributed RFQ',
                        'Approved Request for Quotation' => 'Approved Request for Quotation',
                        'Rejected Request for Quotation' => 'Rejected Request for Quotation',
                    ])
                    ->query(fn ($query, array $data) =>
                        $data['value'] ? $query->where('action', $data['value']) : $query
                    ),

                // AOQ FILTER
                Tables\Filters\SelectFilter::make('aoq_filter')
                    ->label('Abstract of Quotation Actions')
                    ->options([
                        'Supplier Response Created'    => 'Supplier Response Created',
                        'Approved Abstract of Quotation' => 'Approved Abstract of Quotation',
                        'Rejected Abstract of Quotation' => 'Rejected Abstract of Quotation',
                    ])
                    ->query(fn ($query, array $data) =>
                        $data['value'] ? $query->where('action', $data['value']) : $query
                    ),

                // PURCHASE ORDER FILTER
                Tables\Filters\SelectFilter::make('po_filter')
                    ->label('Purchase Order Actions')
                    ->options([
                        'Locked Purchase Order'    => 'Locked Purchase Order',
                        'Set PO Details'           => 'Set PO Details',
                        'Approved Purchase Order'  => 'Approved Purchase Order',
                        'Rejected Purchase Order'  => 'Rejected Purchase Order',
                    ])
                    ->query(fn ($query, array $data) =>
                        $data['value'] ? $query->where('action', $data['value']) : $query
                    ),
            ])

            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHistoryLogs::route('/'),
        ];
    }
}
