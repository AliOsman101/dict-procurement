<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HistoryLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;

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

            // 🧹 Add the Clear Logs button here
            ->headerActions([
                Action::make('clearLogs')
                    ->label('Clear All Logs')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-trash')
                    ->action(function () {
                        ActivityLog::truncate();
                    })
                    ->successNotificationTitle('All history logs have been cleared successfully.'),
            ])

            ->filters([])
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
