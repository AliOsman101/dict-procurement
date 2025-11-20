<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Models\User;
use Filament\Tables\Actions\Action;
use App\Filament\Resources\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Pages\Actions;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('delete')
                ->label('Delete')
                ->requiresConfirmation()
                ->action(function () {
                    $record = $this->record;

                    try {
                        if ($record->delete()) {
                            Notification::make()
                                ->title('Deleted Successfully')
                                ->body('The user record has been deleted.')
                                ->success()
                                ->send();

                            $this->redirect(static::$resource::getUrl('index'));
                        } else {
                            Notification::make()
                                ->title('Deletion Failed')
                                ->body('Unable to delete the user record. Please try again.')
                                ->danger()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error')
                            ->body('An error occurred: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
        ];
    }
}
