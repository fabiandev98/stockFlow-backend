<?php

use App\Http\Middleware\ForceJson;
use App\Http\Middleware\SetApiLocale;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',

        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',

        commands: __DIR__.'/../routes/console.php',

        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            [],
            SymfonyRequest::HEADER_X_FORWARDED_FOR |
            SymfonyRequest::HEADER_X_FORWARDED_HOST |
            SymfonyRequest::HEADER_X_FORWARDED_PORT |
            SymfonyRequest::HEADER_X_FORWARDED_PROTO |
            SymfonyRequest::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->preventRequestsDuringMaintenance([
            '/mtc/*',
        ]);
        $middleware->append(ForceJson::class);

        $middleware->api(prepend: [
            SetApiLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            if (($exception->errorInfo[0] ?? null) !== '23000') {
                return null;
            }

            return response()->json([
                'message' => __('errors.database.delete_restricted'),
            ], Response::HTTP_CONFLICT);
        });
    })->create();
