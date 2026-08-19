<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('X-Frame-Options','DENY');
        $response->headers->set('Referrer-Policy','strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy','camera=(), microphone=(), geolocation=(self)');
        $response->headers->set('Cross-Origin-Opener-Policy','same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy','same-origin');
        if (app()->environment('production')) {
            $response->headers->set('Content-Security-Policy', "default-src 'self'; img-src 'self' data: blob: https://upload.wikimedia.org https://*.tile.openstreetmap.org https://unpkg.com; style-src 'self' 'unsafe-inline' https://unpkg.com; script-src 'self' https://unpkg.com; connect-src 'self' https: wss:; font-src 'self' data: https://unpkg.com; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
            if ($request->isSecure()) $response->headers->set('Strict-Transport-Security','max-age=31536000; includeSubDomains');
        }
        return $response;
    }
}
