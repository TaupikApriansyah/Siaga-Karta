<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Ambulance;
use App\Services\AuditService;
use App\Services\RevisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
class AmbulanceController extends Controller
{
    public function index(){return Ambulance::orderBy('code')->get();}
    public function store(Request $r)
    {
        $d=$r->validate(['code'=>'required|string|max:30|unique:ambulances,code','plate_number'=>'required|string|max:20|unique:ambulances,plate_number','capacity'=>'required|integer|min:1|max:10','status'=>'nullable|in:tersedia,dipesan,bertugas,maintenance','notes'=>'nullable|string|max:1000']);
        $a=Ambulance::create($d);
        AuditService::log($r,'ambulance.created',$a,[],null,$a->only('code','plate_number','capacity','status','notes'));
        RevisionService::bump('operations'); Cache::forget('public.bootstrap');
        return response()->json($a,201);
    }
    public function update(Request $r,Ambulance $ambulance)
    {
        $d=$r->validate(['plate_number'=>'sometimes|string|max:20|unique:ambulances,plate_number,'.$ambulance->id,'capacity'=>'sometimes|integer|min:1|max:10','status'=>'sometimes|in:tersedia,dipesan,bertugas,maintenance','notes'=>'nullable|string|max:1000']);
        $before=$ambulance->only('plate_number','capacity','status','notes');
        $ambulance->update($d);
        AuditService::log($r,'ambulance.updated',$ambulance,[],$before,$ambulance->fresh()->only('plate_number','capacity','status','notes'));
        RevisionService::bump('operations'); Cache::forget('public.bootstrap');
        return $ambulance;
    }
}
