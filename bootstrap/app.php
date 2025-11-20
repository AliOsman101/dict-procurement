<?php

use App\Jobs\AccrueMonthlyLeave;
use App\Jobs\HandleLeaveExpiration;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->booted(function (Application $app) {
        Log::info('Laravel Booted: Executing jobs once and setting up scheduled tasks.');

        // ✅ Run both jobs immediately once when Laravel boots

        //    dispatch(new AccrueMonthlyLeave());
        //   dispatch(new HandleLeaveExpiration());

    })
    ->create();
