<?php

use App\Exceptions\Services\AuthService\AuthorizationException;
use App\Http\Middleware\AuthenticateAdminToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.token' => AuthenticateAdminToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $exception) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (AuthorizationException $exception) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                ],
            ], $exception->getCode());
        });

        $exceptions->render(function (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $exception->status,
                    'message' => $exception->validator->errors()->first(),
                    'fields' => $exception->errors(),
                ],
            ], $exception->status);
        });

        $exceptions->render(function (ThrottleRequestsException $exception) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 429,
                    'message' => 'too_many_login_attempts',
                ],
            ], 429);
        });
    })->create();
