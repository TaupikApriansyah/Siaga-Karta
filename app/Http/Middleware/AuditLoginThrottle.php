<?php
namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuditLoginThrottle
{
    public function handle(Request $request, Closure $next): Response
    {
        $identity = User::normalizeIdentity($request->input('login'));
        $identityHash = hash('sha256', $identity !== '' ? $identity : 'empty');
        $ip = $request->ip() ?: 'unknown';

        $limits = [
            ['key' => 'login:pair:'.hash('sha256', $ip.'|'.$identityHash), 'max' => 6],
            ['key' => 'login:identity:'.$identityHash, 'max' => 12],
            ['key' => 'login:ip:'.$ip, 'max' => 30],
        ];

        $blocked = null;
        $retryAfter = 0;
        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit['key'], $limit['max'])) {
                $blocked = $limit['key'];
                $retryAfter = max($retryAfter, RateLimiter::availableIn($limit['key']));
            }
        }

        if ($blocked !== null) {
            // Keep the audit useful without creating one audit row for every blocked bot request.
            $auditKey = 'login:audit:'.hash('sha256', $ip.'|'.$identityHash);
            if (!RateLimiter::tooManyAttempts($auditKey, 1)) {
                RateLimiter::hit($auditKey, 60);
                AuditService::log($request, 'auth.login_failed', null, [
                    'reason' => 'rate_limited',
                    'login_hash' => $identity !== '' ? hash('sha256', $identity) : null,
                    'retry_after_seconds' => $retryAfter,
                ]);
            }

            return response()->json([
                'message' => 'Terlalu banyak percobaan login. Coba lagi sebentar.',
            ], 429, [
                'Retry-After' => (string) max(1, $retryAfter),
            ]);
        }

        foreach ($limits as $limit) {
            RateLimiter::hit($limit['key'], 60);
        }

        return $next($request);
    }
}
