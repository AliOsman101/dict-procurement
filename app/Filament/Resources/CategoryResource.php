<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Categories';
    protected static ?string $navigationGroup = 'Procurement Management';
    protected static ?int $navigationSort = 7;

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->hasRole('admin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Category Name')
                    ->unique(Category::class, 'name', ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'This category name already exists. Please use a different name.',
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Category Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
               Tables\Actions\EditAction::make()
    ->after(function ($record) {
        $name = $record->name ?? 'Unnamed Category';
        \App\Helpers\ActivityLogger::log(
            'Updated Category',
            "Category '{$name}' was updated."
        );
    })
    ->successNotification(
        Notification::make()
            ->success()
            ->title('Category updated')
            ->body('The category has been updated successfully.'))

                    ->failureNotification(
                        Notification::make()
                            ->danger()
                            ->title('Update failed')
                            ->body('Failed to update the category. Please try again.')
                    )
                    ->before(function (Tables\Actions\EditAction $action, array $data, $record) {
                        // Check if the name is being changed and if it already exists
                        if ($data['name'] !== $record->name) {
                            $exists = Category::where('name', $data['name'])
                                ->where('id', '!=', $record->id)
                                ->exists();
                            
                            if ($exists) {
                                Notification::make()
                                    ->danger()
                                    ->title('Duplicate Category')
                                    ->body('A category with this name already exists. Please use a different name.')
                                    ->persistent()
                                    ->send();
                                
                                $action->halt();
                            }
                        }
                    }),
                Tables\Actions\DeleteAction::make()
    ->after(function ($record) {
        $name = $record->name ?? 'Unnamed Category';
        \App\Helpers\ActivityLogger::log(
            'Deleted Category',
            "Category '{$name}' was deleted."
        );
    })
    ->successNotification(
        Notification::make()
            ->success()
            ->title('Category deleted')
            ->body('The category has been deleted successfully.')
    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
    ->successNotification(function ($records) {
        
        $count = $records->count();
        return Notification::make()
            ->success()
            ->title('Categories deleted')
            ->body("$count categories were deleted by " . auth()->user()->name . ".");
    })

                        ->failureNotification(
                            Notification::make()
                                ->danger()
                                ->title('Delete failed')
                                ->body('Failed to delete some categories. They may be in use.')
                        ),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCategories::route('/'),
        ];
    }
}