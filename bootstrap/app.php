<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AdminPermissionMiddleware;
use App\Http\Middleware\CaptureReferral;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventPrivateIndexing;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            CaptureReferral::class,
            PreventPrivateIndexing::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'admin.permission' => AdminPermissionMiddleware::class,
        ]);

        // Razorpay posts server-to-server — exempt the webhook from CSRF.
        $middleware->validateCsrfTokens(except: [
            'razorpay/webhook',
            'marketing/payment/*/callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response) {
            $path = '/'.ltrim(request()->path(), '/');

            if (! in_array($path, ['/', '/robots.txt', '/sitemap.xml'], true)) {
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
            }

            return $response;
        });
    })->create();
