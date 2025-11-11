<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        Log::info('mutateFormDataBeforeSave called with data:', $data);

        // Log the current hashed password from the database
        Log::info('Current hashed password from the database:', [$this->record->password]);

        // Retrieve the plain text old password
        $oldPassword = $data['old_password'] ?? null;

        // Check if old password matches the hashed password in the database
        if (! empty($oldPassword) && ! Hash::check($oldPassword, $this->record->password)) {
            Log::warning('Old password does not match the current password.');

            throw ValidationException::withMessages([
                'old_password' => 'The provided old password does not match our records.',
            ]);
        }

        // If old password is provided, hash the new password
        if (! empty($oldPassword)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Remove old_password and password_confirmation from data to prevent saving them
        unset($data['old_password'], $data['password_confirmation']);

        Log::info('mutateFormDataBeforeSave modified data:', $data);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record->getKey()]);
    }
}
