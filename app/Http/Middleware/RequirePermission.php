<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->attributes->get('api_user');
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        $granted = config('permissions.roles.'.$user->role, []);
        foreach ($permissions as $permission) {
            if (in_array($permission, $granted, true)) return $next($request);
        }
        return response()->json(['message' => 'Anda tidak memiliki izin untuk aksi ini.'], 403);
    }
}
