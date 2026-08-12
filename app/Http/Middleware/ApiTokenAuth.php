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
        if (!$token || !$token->user || !$token->user->is_active || $token->expires_at->isPast()) {
            return response()->json(['message'=>'Sesi tidak valid atau telah kedaluwarsa.'],401);
        }
        $token->forceFill(['last_used_at'=>now()])->save();
        $request->attributes->set('api_user',$token->user);
        $request->attributes->set('api_token',$token);
        return $next($request);
    }
}
