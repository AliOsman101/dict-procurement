<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set the user_id to the currently authenticated user's ID
        $data['password'] = Hash::make('secret');

        // Set the email_verified_at field to today's date
        $data['email_verified_at'] = now();

        return $data;
    }
}
