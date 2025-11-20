<?php

namespace App\Filament\Resources\Setup\PositionResource\Pages;

use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Setup\PositionResource;

class EditPosition extends EditRecord
{
    protected static string $resource = PositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Position')
                ->modalSubheading('Are you sure you want to delete this position? This action cannot be undone.')
                ->action(function ($record) {
                    // Attempt to delete the record
                    try {
                        if ($record->employees()->exists()) {
                            throw new \Exception('Cannot delete this position because it is linked to existing employees.');
                        }

                        $record->delete();

                        $this->notify('success', 'Position deleted successfully!');
                    } catch (\Exception $e) {
                        // Show an error notification if deletion fails


                        // Notify success
                        Notification::make()
                            ->title('Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        // Redirect to the list page after creation
        return static::getResource()::getUrl('index');
    }
}
