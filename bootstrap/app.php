<?php

use App\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Gateway callbacks post cross-site; their payload is verified by
        // decryption with the tenant's working key instead of CSRF.
        $middleware->validateCsrfTokens(except: [
            '/payment/success',
            '/payment/cancel',
        ]);

        // Landing-page language choice is written by the page's JS too.
        $middleware->encryptCookies(except: [
            \App\Http\Controllers\LandingController::LANG_COOKIE,
        ]);

        // Every /api request is resolved to a tenant (X-Tenant header).
        $middleware->api(prepend: [
            \App\Http\Middleware\ResolveTenant::class,
        ]);

        // The admin tenant must be resolved before route-model binding so
        // bound models are looked up inside the right tenant scope.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\AdminMiddleware::class,
        );

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'admin.locale' => \App\Http\Middleware\SetAdminLocale::class,
            'staff' => \App\Http\Middleware\StaffMiddleware::class,
            'merchant' => \App\Http\Middleware\MerchantMiddleware::class,
            'module' => \App\Http\Middleware\EnsureModuleEnabled::class,
            'cache.api' => \App\Http\Middleware\CachePublicApi::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->render(fn (ApiException $e) => $e->render());

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                    'code' => 'VALIDATION_ERROR',
                ], $e->status);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'code' => 'UNAUTHENTICATED'], 401);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                    'code' => 'TOO_MANY_REQUESTS',
                ], 429, $e->getHeaders());
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getPrevious() instanceof ModelNotFoundException ? 'Resource not found' : 'Not found',
                    'code' => 'NOT_FOUND',
                ], 404);
            }
        });

        // Never leak internal exception messages to API clients unless debugging.
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            // HttpResponseException carries a ready response (e.g. a rate limiter's custom response).
            if (! $isApi($request) || $e instanceof HttpExceptionInterface || $e instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                return null;
            }

            return response()->json(array_filter([
                'success' => false,
                'message' => __('platform.errors.server_error'),
                'code' => 'SERVER_ERROR',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], fn ($v) => $v !== null), 500);
        });
    })->create();
