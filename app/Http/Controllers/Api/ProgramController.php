<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Services\AuditService;
use App\Services\RevisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
class ProgramController extends Controller
{
    public function index(){return Program::latest()->limit(200)->get();}
    public function store(Request $r)
    {
        $d=$r->validate(['code'=>'required|string|max:30|unique:programs,code','name'=>'required|string|max:180','description'=>'nullable|string|max:3000','target_amount'=>'required|integer|min:0','status'=>'nullable|in:aktif,selesai,nonaktif','image_url'=>'nullable|url|max:2000']);
        $d['collected_amount']=0; $d['distributed_amount']=0;
        $p=Program::create($d);
        AuditService::log($r,'program.created',$p,[],null,$p->only('code','name','target_amount','status'));
        RevisionService::bump('operations','finance'); Cache::forget('public.bootstrap');
        return response()->json($p,201);
    }
    public function update(Request $r,Program $program)
    {
        // collected_amount/distributed_amount are ledger-derived and intentionally not editable here.
        $d=$r->validate(['name'=>'sometimes|string|max:180','description'=>'nullable|string|max:3000','target_amount'=>'sometimes|integer|min:0','status'=>'sometimes|in:aktif,selesai,nonaktif','image_url'=>'nullable|url|max:2000']);
        $before=$program->only('name','description','target_amount','status','image_url');
        $program->update($d);
        AuditService::log($r,'program.updated',$program,[],$before,$program->fresh()->only('name','description','target_amount','status','image_url'));
        RevisionService::bump('operations','finance'); Cache::forget('public.bootstrap');
        return $program;
    }
}
