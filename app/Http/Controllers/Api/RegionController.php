<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Services\AuditService;
use App\Services\ReportAccessService;
use App\Services\RevisionService;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    public function index(Request $request)
    {
        $data=$request->validate(['level'=>'nullable|in:kota,kecamatan,kelurahan','parent_id'=>'nullable|integer|exists:regions,id']);
        $q=Region::query()->where('is_active',true)
            ->select('id','code','short_code','name','level','parent_id')
            ->with('parent:id,code,short_code,name,level');
        if(!empty($data['level'])) $q->where('level',$data['level']);
        if(array_key_exists('parent_id',$data)) $q->where('parent_id',$data['parent_id']);
        return response()->json(['regions'=>$q->orderBy('level')->orderBy('name')->get()]);
    }

    public function allowedKelurahan(Request $request)
    {
        $user=$request->attributes->get('api_user');
        $rows=ReportAccessService::allowedKelurahan($user)
            ->with('parent:id,code,short_code,name,level')
            ->orderBy('name')->get(['id','code','short_code','name','level','parent_id']);
        return response()->json(['regions'=>$rows]);
    }

    public function updateLocalStructure(Request $request, Region $region)
    {
        $user=$request->attributes->get('api_user');
        $allowed=$region->level==='kelurahan' && match($user->role) {
            'kota' => true,
            'kecamatan' => (int)$region->parent_id === (int)$user->region_id,
            'kelurahan' => (int)$region->id === (int)$user->region_id,
            default => false,
        };
        if(!$allowed) {
            return response()->json(['message'=>'Wilayah tersebut berada di luar hak akses akun ini.'],403);
        }
        $data=$request->validate(['rt_count'=>'required|integer|min:0|max:999','rw_count'=>'required|integer|min:0|max:999']);
        $before=$region->only('rt_count','rw_count');
        $region->update($data);
        AuditService::log($request,'region.local_structure_updated',$region,[],$before,$region->only('rt_count','rw_count'));
        RevisionService::bump('operations');
        return response()->json(['message'=>'Data jumlah RT/RW kelurahan berhasil diperbarui.','region'=>$region]);
    }
}
