<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // $schedule->command('bluesky:labeler:polling')->hourly();
        $schedule->command('bsky:label-follower')->hourlyAt(25);
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(['*']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
