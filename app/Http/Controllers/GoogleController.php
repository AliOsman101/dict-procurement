<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\NewUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
       return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
{
    $googleUser = Socialite::driver('google')->user();

    // Check if the user already exists and is active
    $existingUser = User::where('email', $googleUser->getEmail())->first();

    if ($existingUser && $existingUser->is_active) {
        Auth::login($existingUser);

        // ✅ Log login activity
        try {
            \App\Models\ActivityLog::create([
                'user_id' => $existingUser->id,
                'role' => $existingUser->roles->pluck('name')->implode(', ') ?? 'Unknown',
                'action' => 'Employee Login',
                'details' => $existingUser->name . ' logged in via Google',
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to record login activity for user {$existingUser->email}: {$e->getMessage()}");
        }

        // Check if the user has an associated employee record
        if ($existingUser->employee === null) {
            // Create a new record
            $employee = Employee::create([
                'user_id' => $existingUser->id,
                'firstname' => $googleUser->user['given_name'],
                'lastname' => $googleUser->user['family_name'],
                'email' => $googleUser->getEmail(),
                'is_active' => true,
            ]);

            return redirect()->route('filament.admin.resources.employees.edit', ['record' => $employee->id])
                ->with('status', 'Please complete your employee profile.');
        }

        return redirect()->intended('admin');
    }

    // If the user is not active or does not exist, add to the new_users table
    $newUser = NewUser::updateOrCreate(
        ['email' => $googleUser->getEmail()],
        [
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->id,
            'name' => $googleUser->getName(),
        ]
    );

    return redirect()->route('filament.admin.auth.login')
        ->with('status', 'Your account is pending approval.');
}
}