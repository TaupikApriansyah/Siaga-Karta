<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
class UserController extends Controller
{
    public function index(){return User::select('id','name','email','username','role','is_active','created_at')->latest()->get();}
    public function store(Request $r){$d=$r->validate(['name'=>'required|string|max:120','email'=>'required|email|max:190|unique:users,email','username'=>'required|string|min:4|max:60|unique:users,username','role'=>'required|in:admin,petugas','password'=>'required|string|min:10|max:200']);$u=User::create($d);AuditService::log($r,'user.created',$u,['role'=>$u->role]);return response()->json($u->only('id','name','email','username','role','is_active'),201);}
    public function update(Request $r,User $user){$d=$r->validate(['name'=>'sometimes|string|max:120','email'=>'sometimes|email|max:190|unique:users,email,'.$user->id,'username'=>'sometimes|string|min:4|max:60|unique:users,username,'.$user->id,'role'=>'sometimes|in:admin,petugas','is_active'=>'sometimes|boolean','password'=>'nullable|string|min:10|max:200']);if(empty($d['password']))unset($d['password']);$user->update($d);AuditService::log($r,'user.updated',$user);return $user->only('id','name','email','username','role','is_active');}
}
