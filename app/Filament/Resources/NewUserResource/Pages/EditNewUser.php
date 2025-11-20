<?php

namespace App\Filament\Resources\NewUserResource\Pages;

use App\Filament\Resources\NewUserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EditNewUser extends EditRecord
{
    protected static string $resource = NewUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->action('approveUser')
                ->requiresConfirmation()
                ->color('success'),

            Actions\DeleteAction::make(),
        ];
    }

    public function approveUser()
    {
        DB::transaction(function () {
            $newUser = $this->record;

            // Check if the user already exists
            $user = User::where('email', $newUser->email)->first();

            if ($user) {
                // Update the existing user
                $user->update([
                    'name' => $newUser->name,
                    'google_id' => $newUser->google_id,
                    'email_verified_at' => now(),
                    'password' => Hash::make(uniqid()),
                ]);

                Notification::make()
                    ->title('User Created')
                    ->body('New user created and approved successfully.')
                    ->success()
                    ->send();
                //   $this->notify('success', 'Existing user updated successfully.');
            } else {
                // Transfer data from NewUser to User
                User::create([
                    'name' => $newUser->name,
                    'email' => $newUser->email,
                    'google_id' => $newUser->google_id,
                    'email_verified_at' => now(),
                    'password' => Hash::make(uniqid()),
                ]);

                //   $this->notify('success', 'New user created and approved successfully.');
            }

            // Delete the NewUser record
            $newUser->delete();
        });
    }
}
