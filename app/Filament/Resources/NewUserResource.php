<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\NewUser;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\NewUserResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\NewUserResource\RelationManagers;
use Filament\Tables\Actions\Action; // Correct import for Action

class NewUserResource extends Resource
{
    protected static ?string $model = NewUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Admin';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('google_id')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('google_id')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->action(function (NewUser $record) {
                        // Check if the user already exists based on email or Google ID.
                        $user = User::where('email', $record->email)
                            ->orWhere('google_id', $record->google_id)
                            ->first();
            
                        if ($user) {
                            // Update the existing user.
                            $user->name = $record->name;
                            $user->google_id = $record->google_id;
                            $user->save();
                        } else {
                            // Create a new user.
                            $user = new User();
                            $user->name = $record->name;
                            $user->email = $record->email;
                            $user->google_id = $record->google_id;
                            $user->password = Hash::make('secret');
                            $user->is_active = 1;
                            $user->save();
            
                            // Assign 'user' role by default.
                            $user->assignRole('user');
                        }
            
                        // Send email notification to the approved user.
                        \Illuminate\Support\Facades\Mail::to($user->email)
                            ->queue(new \App\Mail\NewUserApproved($user));
            
                        // Delete the record from the newuser table.
                        $record->delete();
                    })
                    ->requiresConfirmation()
                    ->color('success'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
            
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewUsers::route('/'),


        ];
    }
}
