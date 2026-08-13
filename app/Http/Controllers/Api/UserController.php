<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\RevisionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $r)
    {
        $d=$r->validate(['per_page'=>'nullable|integer|min:10|max:100','page'=>'nullable|integer|min:1']);
        $perPage=min(max((int)($d['per_page']??25),10),100);
        return User::select('id','name','email','username','role','is_active','created_at')->latest('id')->paginate($perPage);
    }

    public function store(Request $r)
    {
        $r->merge([
            'email'=>User::normalizeIdentity($r->input('email')),
            'username'=>User::normalizeIdentity($r->input('username')),
        ]);
        $d=$r->validate([
            'name'=>'required|string|max:120',
            'email'=>'required|email|max:190|unique:users,email',
            'username'=>'required|string|min:4|max:60|regex:/^[a-z0-9._-]+$/|unique:users,username',
            'role'=>'required|in:admin,petugas',
            'password'=>'required|string|min:10|max:200',
        ]);
        $u=User::create($d);
        AuditService::log($r,'user.created',$u,['role'=>$u->role],null,$u->only('name','email','username','role','is_active'));
        RevisionService::bump('users');
        return response()->json($u->only('id','name','email','username','role','is_active'),201);
    }

    public function update(Request $r, User $user)
    {
        if($r->has('email')) $r->merge(['email'=>User::normalizeIdentity($r->input('email'))]);
        if($r->has('username')) $r->merge(['username'=>User::normalizeIdentity($r->input('username'))]);

        $d=$r->validate([
            'name'=>'sometimes|string|max:120',
            'email'=>['sometimes','email','max:190',Rule::unique('users','email')->ignore($user->id)],
            'username'=>['sometimes','string','min:4','max:60','regex:/^[a-z0-9._-]+$/',Rule::unique('users','username')->ignore($user->id)],
            'role'=>'sometimes|in:admin,petugas',
            'is_active'=>'sometimes|boolean',
            'password'=>'nullable|string|min:10|max:200',
        ]);
        if(empty($d['password'])) unset($d['password']);

        $actor=$r->attributes->get('api_user');
        $willRemoveAdmin = $user->role==='admin' && (($d['role']??'admin')!=='admin' || (array_key_exists('is_active',$d) && !$d['is_active']));
        if($willRemoveAdmin && User::where('role','admin')->where('is_active',true)->count() <= 1) {
            return response()->json(['message'=>'Minimal satu Admin aktif harus tetap tersedia.'],422);
        }
        if($actor?->id===$user->id && array_key_exists('is_active',$d) && !$d['is_active']) {
            return response()->json(['message'=>'Anda tidak dapat menonaktifkan akun yang sedang digunakan.'],422);
        }

        $before=$user->only('name','email','username','role','is_active');
        $passwordChanged=array_key_exists('password',$d);
        $willDeactivate=array_key_exists('is_active',$d) && !$d['is_active'];
        $user->update($d);
        $sessionsRevoked=$passwordChanged || $willDeactivate;
        if($sessionsRevoked) $user->apiTokens()->delete();
        $after=$user->fresh()->only('name','email','username','role','is_active');
        AuditService::log($r,'user.updated',$user,['sessions_revoked'=>$sessionsRevoked], $before, $after);
        RevisionService::bump('users');
        return $user->only('id','name','email','username','role','is_active');
    }
}
