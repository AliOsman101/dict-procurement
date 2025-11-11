<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\UserResource\Pages;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

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
                    ->unique(User::class, 'email', fn($record) => $record)

                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('roles')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->preload()

                    ->disabled(fn($livewire) => !auth()->user()->hasRole('admin'))

                    ->required(),

                Forms\Components\Checkbox::make('is_active')
                    ->visible(fn($livewire) => auth()->user()->hasRole('admin'))
                    ->disabled(fn($livewire) => !auth()->user()->hasRole('admin')),


            ]);
    }

    public static function table(Table $table): Table
    {
        Log::info('UserResource table method called');

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->numeric()
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles')
                    ->label('Roles')
                    ->getStateUsing(function (User $record) {
                        return $record->roles->pluck('name')->join(', ');
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('google_id')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\CheckboxColumn::make('is_active')
                    ->visible(fn($livewire) => auth()->user()->hasRole('admin'))
                    ->disabled(fn($livewire) => !auth()->user()->hasRole('admin')),

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

                Filter::make('is_active')
                    ->query(fn(Builder $query): Builder => $query->where('is_active', true))
                    ->label('Active Users'),

            ])
            ->actions([

                Tables\Actions\EditAction::make()
                    ->authorize(fn(User $record) => auth()->user()->can('update', $record)),

                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn(User $record) => static::getUrl('view', ['record' => $record->id]))
                    ->openUrlInNewTab(false),


            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])->modifyQueryUsing(function (Builder $query) {
                Log::info('UserResource modifyQueryUsing method called');

                $user = auth()->user();
                Log::info('User Info', ['id' => $user->id, 'roles' => $user->getRoleNames()]);

                // Non-admin users should only see their own record
                if (!$user->hasRole('admin')) {
                    Log::info('Non-admin user, modifying query', ['user_id' => $user->id]);
                    $query->where('id', $user->id);
                } else {
                    Log::info('Admin user, no query modification');
                }

                return $query;
            });
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            'view' => Pages\ViewUser::route('/{record}'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        // Show navigation if the user is an admin or has an associated employee
        return $user?->hasAnyRole(['hr-admin', 'admin']);
    }
}
