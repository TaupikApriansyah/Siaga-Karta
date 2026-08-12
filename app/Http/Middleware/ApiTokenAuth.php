<?php
namespace App\Http\Middleware;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->bearerToken();
        if (!$raw) return response()->json(['message'=>'Unauthenticated.'],401);
        $token = ApiToken::with('user')->where('token_hash', hash('sha256',$raw))->first();
        $expired = !$token || $token->expires_at?->isPast() || ($token->absolute_expires_at && $token->absolute_expires_at->isPast());
        if ($expired || !$token?->user || !$token->user->is_active) {
            $token?->delete();
            return response()->json(['message'=>'Sesi tidak valid atau telah kedaluwarsa.'],401);
        }
        // Avoid a database write on every API request. Refresh last_used_at at most once per 5 minutes.
        if(!$token->last_used_at || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at'=>now()])->save();
        }
        $request->attributes->set('api_user',$token->user);
        $request->attributes->set('api_token',$token);
        return $next($request);
    }
}
