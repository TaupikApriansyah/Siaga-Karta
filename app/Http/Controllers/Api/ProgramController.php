<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Services\AuditService;
use Illuminate\Http\Request;
class ProgramController extends Controller
{
    public function index(){return Program::latest()->get();}
    public function store(Request $r){$d=$r->validate(['code'=>'required|string|max:30|unique:programs,code','name'=>'required|string|max:180','description'=>'nullable|string|max:3000','target_amount'=>'required|integer|min:0','collected_amount'=>'nullable|integer|min:0','distributed_amount'=>'nullable|integer|min:0','status'=>'nullable|in:aktif,selesai,nonaktif','image_url'=>'nullable|url|max:2000']);$p=Program::create($d);AuditService::log($r,'program.created',$p);return response()->json($p,201);}
    public function update(Request $r,Program $program){$d=$r->validate(['name'=>'sometimes|string|max:180','description'=>'nullable|string|max:3000','target_amount'=>'sometimes|integer|min:0','collected_amount'=>'sometimes|integer|min:0','distributed_amount'=>'sometimes|integer|min:0','status'=>'sometimes|in:aktif,selesai,nonaktif','image_url'=>'nullable|url|max:2000']);$program->update($d);AuditService::log($r,'program.updated',$program);return $program;}
}
