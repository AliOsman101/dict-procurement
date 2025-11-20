<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Define roles
        $roles = [
            'user',
            'admin',
            'leave-admin',
            'dtr-admin',
            'to-admin',
            'hr-admin',
            'procurement-admin',
        ];

        // Create roles if they don't exist
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Create or update admin user
        $adminUser = User::updateOrCreate(
            [
                'email' => 'admin@gmail.com',
            ],
            [
                'name' => 'Admin',
                'google_id' => '115098783060946018090',
                'password' => Hash::make('secret'),
                'is_active' => 1,
            ]
        );
        $adminUser->assignRole('admin');

        // Ensure every user has an employee record
        $users = User::all();
        foreach ($users as $user) {
           Employee::firstOrCreate(
    [
        'user_id' => $user->id,
    ],
    [
        'firstname'     => explode(' ', $user->name)[0] ?? 'First',
        'middlename'    => '',
        'lastname'      => explode(' ', $user->name)[1] ?? 'Last',
        'employee_no'   => 1000 + $user->id, // ✅ numeric instead of string
        'civil_status'  => 'single',
        'employment_status' => 'plantilla',
        'gender'        => 'male',
        'designation'   => 'Staff',
        'division_id'   => 1,
        'position_id'   => 1,
        'project_id'    => 1,
        'office'        => 'Region Office',
        'birthday'      => '1990-01-01',
        'mobile'        => '09123456789',
        'photo'         => '/uploads/profilepicture/profile.png',
        'is_active'     => 1,
    ]
);

        }
    }
}