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
        $user=User::where('email',strtolower($data['login']))->orWhere('username',$data['login'])->first();
        if (!$user || !$user->is_active || !Hash::check($data['password'],$user->password)) {
            return response()->json(['message'=>'Email/username atau password salah.'],422);
        }
        ApiToken::where('user_id',$user->id)->where('expires_at','<',now())->delete();
        $plain=Str::random(80);
        ApiToken::create([
            'user_id'=>$user->id,'token_hash'=>hash('sha256',$plain),'expires_at'=>now()->addHours(12),
            'ip_address'=>$request->ip(),'user_agent'=>substr((string)$request->userAgent(),0,1000),
        ]);
        $request->attributes->set('api_user',$user);
        AuditService::log($request,'auth.login',$user);
        return response()->json(['token'=>$plain,'token_type'=>'Bearer','expires_in'=>43200,'user'=>$this->userData($user)]);
    }
    public function me(Request $request){ return response()->json(['user'=>$this->userData($request->attributes->get('api_user'))]); }
    public function logout(Request $request)
    {
        AuditService::log($request,'auth.logout',$request->attributes->get('api_user'));
        $request->attributes->get('api_token')?->delete();
        return response()->json(['message'=>'Logout berhasil.']);
    }
    private function userData(User $u): array { return ['id'=>$u->id,'name'=>$u->name,'email'=>$u->email,'username'=>$u->username,'role'=>$u->role]; }
}
