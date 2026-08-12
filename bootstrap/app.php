<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API-only backend, no "login" web route — never attempt a redirect for an
        // unauthenticated request, regardless of the client's Accept header.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This is an API-only backend — there's no "login" web route to redirect to,
        // so an unauthenticated request must always get a JSON 401, not a redirect.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        });
    })->create();
