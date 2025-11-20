<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Logout;

class LogUserLogout
{
    public function handle(Logout $event): void
    {
        $user = $event->user;

        if ($user) {
            ActivityLog::create([
                'user_id' => $user->id,
                'role' => $user->roles->pluck('name')->implode(', ') ?? 'Unknown',
                'action' => 'Logout',
                'details' => $user->name . ' has logged out.',
                'ip_address' => request()->ip(),
            ]);
        }
    }
}
