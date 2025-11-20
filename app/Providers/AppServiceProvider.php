<?php

namespace App\Providers;

use App\Http\Middleware\CheckRole;
use App\Models\RfqResponse;
use App\Models\Procurement;
use App\Policies\RfqResponsePolicy;
use App\Policies\ProcurementPolicy;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as FilamentLoginResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Route::middleware('role', CheckRole::class);

        // Register policies using Gate
        Gate::policy(RfqResponse::class, RfqResponsePolicy::class);
        Gate::policy(Procurement::class, ProcurementPolicy::class);

        Livewire::component('tie-breaking-animation', \App\Livewire\TieBreakingAnimation::class);
    }

    public function register()
    {
        // Bind the custom login response
        // $this->app->bind(FilamentLoginResponse::class, \App\Http\Responses\CustomLoginResponse::class);
    }
}