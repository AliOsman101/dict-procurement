<?php

namespace App\Filament\Resources\ProcurementResource\Pages;

use App\Filament\Resources\ProcurementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use Filament\Notifications\Notification;

class EditProcurement extends EditRecord
{
    protected static string $resource = ProcurementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function ($record) {
                    // 🔹 Log Deletion
                    $user = Auth::user();

                    ActivityLog::create([
                        'user_id' => $user->id,
                        'role' => $user->roles->pluck('name')->implode(', ') ?? 'Unknown',
                        'action' => 'Deleted Procurement',
                        'details' => $record->procurement_id,
                        'ip_address' => request()->ip(),
                    ]);
                }),
        ];
    }

    /**
     * Make form fields read-only for unauthorized users.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = auth()->user();

        if (! $user->can('update', $this->record)) {
            $this->form->disabled();

            Notification::make()
                ->title('Read-Only Access')
                ->body('You are not assigned to this procurement and cannot make changes.')
                ->warning()
                ->send();
        }

        return $data;
    }

    /**
     * 🔹 Log when a procurement record is updated.
     */
    protected function afterSave(): void
    {
        $user = Auth::user();

        ActivityLog::create([
            'user_id' => $user->id,
            'role' => $user->roles->pluck('name')->implode(', ') ?? 'Unknown',
            'action' => 'Updated Procurement',
            'details' => $this->record->procurement_id,
            'ip_address' => request()->ip(),
        ]);
    }
}
