<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class RequireRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->attributes->get('api_user');
        if (!$user || !in_array($user->role,$roles,true)) return response()->json(['message'=>'Anda tidak memiliki hak akses.'],403);
        return $next($request);
    }
}
