<?php

namespace App\Providers;

use Illuminate\Auth\Events\Logout;
use App\Listeners\LogUserLogout;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Logout::class => [
            LogUserLogout::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
