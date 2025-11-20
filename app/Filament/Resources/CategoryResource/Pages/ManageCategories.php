<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use App\Helpers\ActivityLogger;

class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Category created')
                        ->body('The category has been created successfully.')
                )
                ->failureNotification(
                    Notification::make()
                        ->danger()
                        ->title('Creation failed')
                        ->body('Failed to create the category. Please try again.')
                )
                ->before(function (Actions\CreateAction $action, array $data) {
                    $exists = Category::where('name', $data['name'])->exists();

                    if ($exists) {
                        Notification::make()
                            ->danger()
                            ->title('Duplicate Category')
                            ->body('A category with this name already exists. Please use a different name.')
                            ->persistent()
                            ->send();

                        $action->halt();
                    }
                })
                ->using(function (array $data) {
                    try {
                        $category = Category::create($data);

                        // ✅ Log creation
                        ActivityLogger::log(
                            'Created Category',
                            'Category "' . $category->name . '" was created.'
                        );

                        return $category;
                    } catch (QueryException $e) {
                        if ($e->errorInfo[1] == 1062) {
                            Notification::make()
                                ->danger()
                                ->title('Duplicate Category')
                                ->body('A category with this name already exists. Please use a different name.')
                                ->persistent()
                                ->send();

                            throw new \Exception('Duplicate category name');
                        }

                        throw $e;
                    }
                }),
        ];
    }

    // ✅ Add table actions for edit/delete logging
    protected function getTableActions(): array
    {
        return [
            Actions\EditAction::make()
                ->after(function ($record) {
                    $name = $record->name ?? 'Unnamed Category';
                    ActivityLogger::log('Updated Category', "Category '{$name}' was updated.");
                }),

            Actions\DeleteAction::make()
                ->after(function ($record) {
                    $name = $record->name ?? 'Unnamed Category';
                    ActivityLogger::log('Deleted Category', "Category '{$name}' was deleted.");
                }),
        ];
    }
}
