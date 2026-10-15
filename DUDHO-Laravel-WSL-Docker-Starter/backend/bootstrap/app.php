<?php

use App\Exceptions\DairyIdempotencyConflictException;
use App\Exceptions\OtpIdempotencyConflictException;
use App\Exceptions\OtpRateLimitException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = 500;
            $message = 'An unexpected error occurred.';
            $errors = ['server' => ['An unexpected error occurred.']];
            $meta = [];
            $headers = [];

            if ($exception instanceof ValidationException) {
                $status = $exception->status;
                $message = 'Validation failed.';
                $errors = $exception->errors();
            } elseif ($exception instanceof AuthenticationException) {
                $status = 401;
                $message = 'Unauthenticated.';
                $errors = ['token' => ['The provided token is invalid or expired.']];
            } elseif ($exception instanceof OtpIdempotencyConflictException
                || $exception instanceof DairyIdempotencyConflictException) {
                $status = 409;
                $message = 'The request conflicts with a previous use of this client UUID.';
                $errors = ['client_uuid' => ['Use a new client UUID for a different request.']];
            } elseif ($exception instanceof OtpRateLimitException) {
                $status = 429;
                $message = 'Too many requests.';
                $errors = ['rate_limit' => ['Too many requests.']];
                $meta = ['retry_after_seconds' => $exception->retryAfterSeconds];
                $headers['Retry-After'] = (string) $exception->retryAfterSeconds;
            } elseif ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $headers = $exception->getHeaders();

                if ($status === 401) {
                    $message = 'Unauthenticated.';
                    $errors = ['token' => ['The provided token is invalid or expired.']];
                } elseif ($status === 403) {
                    $message = 'Forbidden.';
                    $errors = ['access' => ['You are not authorized to perform this action.']];
                } elseif ($status === 404) {
                    $message = 'Not found.';
                    $errors = ['resource' => ['The requested resource was not found.']];
                } elseif ($status === 409) {
                    $message = 'Conflict.';
                    $errors = ['conflict' => ['The request conflicts with the current resource state.']];
                } elseif ($status === 429) {
                    $message = 'Too many requests.';
                    $errors = ['rate_limit' => ['Too many requests.']];
                    if (isset($headers['Retry-After']) && is_numeric($headers['Retry-After'])) {
                        $meta = ['retry_after_seconds' => (int) $headers['Retry-After']];
                    }
                } elseif ($status < 500) {
                    $message = 'The request could not be completed.';
                    $errors = ['request' => ['The request could not be completed.']];
                }
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => (object) $errors,
                'meta' => (object) $meta,
            ], $status, $headers);
        });
    })->create();
