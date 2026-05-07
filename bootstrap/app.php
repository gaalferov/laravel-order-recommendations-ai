<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Prevent PHP from emitting E_DEPRECATED / E_USER_DEPRECATED messages into the
// HTTP response body (PHP 8.5 flagged deprecations inside upstream vendor code,
// e.g. vendor/laravel/framework/config/database.php, which corrupted JSON
// responses from /api/stripe/webhook). Laravel's HandleExceptions bootstrapper
// still receives deprecation notices via set_error_handler() and routes them to
// the configured "deprecations" log channel, so they remain observable in logs.
error_reporting(error_reporting() & ~E_DEPRECATED & ~E_USER_DEPRECATED);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
