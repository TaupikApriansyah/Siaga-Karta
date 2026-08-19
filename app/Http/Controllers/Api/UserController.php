<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Region;
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
        return User::query()
            ->select('id','name','email','username','role','region_id','is_active','created_at')
            ->with(['region:id,code,short_code,name,level,parent_id'])
            ->latest('id')->paginate($perPage);
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
            'role'=>'required|in:kecamatan,kelurahan',
            'region_id'=>'required|integer|exists:regions,id',
            'password'=>'required|string|min:10|max:200',
        ]);
        $this->assertRegionMatchesRole((int)$d['region_id'],$d['role']);

        $u=User::create($d);
        $u->load('region:id,code,short_code,name,level,parent_id');
        AuditService::log($r,'user.created',$u,['role'=>$u->role,'region_id'=>$u->region_id],null,$u->only('name','email','username','role','region_id','is_active'));
        RevisionService::bump('users');
        return response()->json($this->userPayload($u),201);
    }

    public function update(Request $r, User $user)
    {
        if($r->has('email')) $r->merge(['email'=>User::normalizeIdentity($r->input('email'))]);
        if($r->has('username')) $r->merge(['username'=>User::normalizeIdentity($r->input('username'))]);

        $d=$r->validate([
            'name'=>'sometimes|string|max:120',
            'email'=>['sometimes','email','max:190',Rule::unique('users','email')->ignore($user->id)],
            'username'=>['sometimes','string','min:4','max:60','regex:/^[a-z0-9._-]+$/',Rule::unique('users','username')->ignore($user->id)],
            'role'=>'sometimes|in:kota,kecamatan,kelurahan',
            'region_id'=>'sometimes|nullable|integer|exists:regions,id',
            'is_active'=>'sometimes|boolean',
            'password'=>'nullable|string|min:10|max:200',
        ]);
        if(empty($d['password'])) unset($d['password']);

        $actor=$r->attributes->get('api_user');
        if($user->role!=='kota' && (($d['role']??$user->role)==='kota')) {
            return response()->json(['message'=>'Role Kota tidak dapat dibuat melalui Manajemen Pengguna.'],422);
        }
        if($user->role==='kota' && (($d['role']??'kota')!=='kota' || (array_key_exists('is_active',$d) && !$d['is_active']))) {
            return response()->json(['message'=>'Akun Kota utama tidak dapat diturunkan role atau dinonaktifkan dari panel ini.'],422);
        }
        if($actor?->id===$user->id && array_key_exists('is_active',$d) && !$d['is_active']) {
            return response()->json(['message'=>'Anda tidak dapat menonaktifkan akun yang sedang digunakan.'],422);
        }

        $nextRole=$d['role']??$user->role;
        $nextRegion=array_key_exists('region_id',$d)?$d['region_id']:$user->region_id;
        if($nextRole==='kota') {
            if($nextRegion) $this->assertRegionMatchesRole((int)$nextRegion,'kota');
        } else {
            if(!$nextRegion) return response()->json(['message'=>'Wilayah wajib dipilih untuk akun Kecamatan/Kelurahan.'],422);
            $this->assertRegionMatchesRole((int)$nextRegion,$nextRole);
        }

        $before=$user->only('name','email','username','role','region_id','is_active');
        $passwordChanged=array_key_exists('password',$d);
        $willDeactivate=array_key_exists('is_active',$d) && !$d['is_active'];
        $user->update($d);
        $sessionsRevoked=$passwordChanged || $willDeactivate;
        if($sessionsRevoked) $user->apiTokens()->delete();
        $user->load('region:id,code,short_code,name,level,parent_id');
        $after=$user->fresh()->only('name','email','username','role','region_id','is_active');
        AuditService::log($r,'user.updated',$user,['sessions_revoked'=>$sessionsRevoked], $before, $after);
        RevisionService::bump('users');
        return response()->json($this->userPayload($user));
    }

    private function userPayload(User $user): array
    {
        return [
            'id'=>$user->id,
            'name'=>$user->name,
            'email'=>$user->email,
            'username'=>$user->username,
            'role'=>$user->role,
            'region_id'=>$user->region_id,
            'is_active'=>(bool)$user->is_active,
            'region'=>$user->region ? [
                'id'=>$user->region->id,
                'code'=>$user->region->code,
                'short_code'=>$user->region->short_code,
                'name'=>$user->region->name,
                'level'=>$user->region->level,
                'parent_id'=>$user->region->parent_id,
            ] : null,
        ];
    }

    private function assertRegionMatchesRole(int $regionId, string $role): void
    {
        $region=Region::findOrFail($regionId);
        if($region->level!==$role) {
            abort(422,"Wilayah yang dipilih harus bertipe {$role} untuk role {$role}.");
        }
        if(!$region->is_active) abort(422,'Wilayah yang dipilih sedang nonaktif.');
    }
}
