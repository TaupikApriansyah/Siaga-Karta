<?php
use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\AuditLoginThrottle;
use App\Http\Middleware\RequestContext;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trusted = array_values(array_filter(array_map('trim', explode(',', (string)env('TRUSTED_PROXIES','127.0.0.1,::1')))));
        $middleware->trustProxies(at: $trusted);
        $middleware->append(RequestContext::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias(['api.token'=>ApiTokenAuth::class,'role'=>RequireRole::class,'audit.login.throttle'=>AuditLoginThrottle::class,'permission'=>RequirePermission::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
