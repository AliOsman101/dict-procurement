<?php

namespace App\Filament\Resources\ProcurementResource\Pages;

use App\Filament\Resources\ProcurementResource;
use App\Models\HistoryLog;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListProcurements extends ListRecords
{
    protected static string $resource = ProcurementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Actions\ViewAction::make(),
            
            // ✅ Edit Action with logging
            Actions\EditAction::make()
                ->after(function ($record) {
                    HistoryLog::create([
                        'user_name' => Auth::user()->name ?? 'Unknown User',
                        'action' => 'Edited a procurement record',
                        'description' => 'Edited procurement titled: ' . $record->title,
                    ]);
                }),

            // ✅ Delete Action with logging
            Actions\DeleteAction::make()
                ->after(function ($record) {
                    HistoryLog::create([
                        'user_name' => Auth::user()->name ?? 'Unknown User',
                        'action' => 'Deleted a procurement record',
                        'description' => 'Deleted procurement titled: ' . $record->title,
                    ]);
                }),
        ];
    }
}
