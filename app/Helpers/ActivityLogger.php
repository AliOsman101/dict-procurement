<?php

namespace App\Helpers;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public static function log($action, $details = null)
    {
        $user = Auth::user();

        ActivityLog::create([
            'user_id'   => $user?->id,
            'action'    => $action,
            'details'   => $details,
            'ip_address'=> request()->ip(),
        ]);
    }
}
