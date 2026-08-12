<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data=$request->validate(['login'=>'required|string|max:190','password'=>'required|string|min:8|max:200']);
        $login=User::normalizeIdentity($data['login']);
        $user=User::query()->where('email',$login)->orWhere('username',$login)->first();

        if (!$user) {
            // Keep the failure path closer to a real password check to reduce username-enumeration timing signal.
            Hash::check($data['password'], '$2y$12$qo6k0BMnA1v/9rpb6GvoxumCgEPSO.GkkjMyl/EgKLZWlUIM/V7e6');
            AuditService::log($request,'auth.login_failed',null,['reason'=>'not_found','login_hash'=>hash('sha256',$login)]);
            return $this->invalidCredentials();
        }
        $passwordValid=Hash::check($data['password'],$user->password);
        if (!$passwordValid) {
            AuditService::log($request,'auth.login_failed',$user,['reason'=>'bad_password']);
            return $this->invalidCredentials();
        }
        if (!$user->is_active) {
            AuditService::log($request,'auth.login_failed',$user,['reason'=>'inactive']);
            return $this->invalidCredentials();
        }

        ApiToken::where('user_id',$user->id)
            ->where(function($q){$q->where('expires_at','<',now())->orWhere('absolute_expires_at','<',now());})
            ->delete();

        $plain=Str::random(80);
        $absolute=now()->addHours(max(1,config('siagakarta.auth.absolute_hours',12)));
        $idle=now()->addMinutes(max(10,config('siagakarta.auth.idle_minutes',60)));
        if($idle->gt($absolute)) $idle=$absolute->copy();
        $token=ApiToken::create([
            'user_id'=>$user->id,
            'token_hash'=>hash('sha256',$plain),
            'expires_at'=>$idle,
            'absolute_expires_at'=>$absolute,
            'last_used_at'=>now(),
            'ip_address'=>$request->ip(),
            'user_agent'=>substr((string)$request->userAgent(),0,1000),
        ]);
        $maxTokens=max(1,config('siagakarta.auth.max_active_tokens',8));
        $staleIds=ApiToken::where('user_id',$user->id)->where('id','!=',$token->id)->latest('id')->skip($maxTokens-1)->pluck('id');
        if($staleIds->isNotEmpty()) ApiToken::whereIn('id',$staleIds)->delete();
        $request->attributes->set('api_user',$user);
        $request->attributes->set('api_token',$token);
        AuditService::log($request,'auth.login',$user);
        return response()->json($this->tokenResponse($plain,$token,$user));
    }

    public function refresh(Request $request)
    {
        $token=$request->attributes->get('api_token');
        $absolute=$token->absolute_expires_at ?? $token->expires_at;
        if(!$absolute || $absolute->isPast()) {
            $token?->delete();
            return response()->json(['message'=>'Sesi telah berakhir. Silakan login kembali.'],401);
        }
        $next=now()->addMinutes(max(10,config('siagakarta.auth.idle_minutes',60)));
        if($next->gt($absolute)) $next=$absolute->copy();
        $token->forceFill(['expires_at'=>$next,'last_used_at'=>now()])->save();
        return response()->json([
            'message'=>'Sesi diperpanjang.',
            'expires_in'=>max(0,now()->diffInSeconds($next,false)),
            'expires_at'=>$next->toIso8601String(),
            'absolute_expires_at'=>$absolute->toIso8601String(),
        ]);
    }

    public function me(Request $request)
    {
        $token=$request->attributes->get('api_token');
        return response()->json([
            'user'=>$this->userData($request->attributes->get('api_user')),
            'expires_at'=>$token?->expires_at?->toIso8601String(),
            'absolute_expires_at'=>$token?->absolute_expires_at?->toIso8601String(),
        ]);
    }

    public function logout(Request $request)
    {
        AuditService::log($request,'auth.logout',$request->attributes->get('api_user'));
        $request->attributes->get('api_token')?->delete();
        return response()->json(['message'=>'Logout berhasil.']);
    }

    private function invalidCredentials()
    {
        return response()->json(['message'=>'Email/username atau password salah.'],422);
    }

    private function tokenResponse(string $plain, ApiToken $token, User $user): array
    {
        return [
            'token'=>$plain,
            'token_type'=>'Bearer',
            'expires_in'=>max(0,now()->diffInSeconds($token->expires_at,false)),
            'expires_at'=>$token->expires_at->toIso8601String(),
            'absolute_expires_at'=>$token->absolute_expires_at?->toIso8601String(),
            'user'=>$this->userData($user),
        ];
    }

    private function userData(User $u): array
    {
        return ['id'=>$u->id,'name'=>$u->name,'email'=>$u->email,'username'=>$u->username,'role'=>$u->role];
    }
}
