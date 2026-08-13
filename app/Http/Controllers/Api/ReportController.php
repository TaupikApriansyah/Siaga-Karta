<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ambulance;
use App\Models\Citizen;
use App\Models\Driver;
use App\Models\Report;
use App\Models\SystemRevision;
use App\Models\StatusHistory;
use App\Services\AuditService;
use App\Services\CitizenService;
use App\Services\NikValidator;
use App\Services\RevisionService;
use App\Services\ScheduleService;
use App\Services\StatusHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $filters=$request->validate([
            'status'=>'nullable|in:menunggu,diproses,dijemput,selesai,ditolak',
            'category'=>'nullable|in:ambulans,bpjs,bencana',
            'per_page'=>'nullable|integer|min:10|max:100',
            'page'=>'nullable|integer|min:1',
        ]);
        $q=Report::query()->select([
            'id','code','citizen_id','ambulance_id','driver_id','type','category','source','status','pickup_location','medical_condition',
            'scheduled_at','service_start_at','service_end_at','created_at'
        ])->with(['citizen:id,name,phone_last4','ambulance:id,code,plate_number','driver:id,code,name'])->latest();
        if(!empty($filters['status'])) $q->where('status',$filters['status']);
        if(!empty($filters['category'])) $q->where('category',$filters['category']);
        return response()->json($q->paginate(min(max((int)($filters['per_page']??25),10),100)));
    }

    public function show(Report $report)
    {
        $report->load(['citizen','ambulance','driver','handler:id,name','verifier:id,name']);
        $nik=$report->citizen->getNik();
        $history=StatusHistory::where('subject_type',Report::class)->where('subject_id',$report->id)
            ->with('changedBy:id,name')->latest('id')->limit(20)->get()->map(fn($h)=>[
                'from_status'=>$h->from_status,'to_status'=>$h->to_status,'reason'=>$h->reason,
                'changed_by'=>$h->changedBy?->name,'created_at'=>$h->created_at,
            ]);
        return response()->json(['report'=>[
            'code'=>$report->code,'name'=>$report->citizen->name,'phone'=>$report->citizen->getPhone(),
            'nik_masked'=>substr($nik,0,6).'******'.substr($nik,-4),
            'type'=>$report->type,'category'=>$report->category,'source'=>$report->source,'status'=>$report->status,
            'pickup_location'=>$report->pickup_location,'destination'=>$report->destination,'medical_condition'=>$report->medical_condition,
            'scheduled_at'=>$report->scheduled_at,'service_start_at'=>$report->service_start_at,'service_end_at'=>$report->service_end_at,
            'ambulance'=>$report->ambulance,'driver'=>$report->driver,'created_at'=>$report->created_at,'status_history'=>$history,
        ]]);
    }

    public function storeManual(Request $request)
    {
        $data=$request->validate([
            'request_uuid'=>'nullable|uuid','source'=>'required|in:datang_langsung,whatsapp,telepon','category'=>'nullable|in:ambulans,bpjs,bencana',
            'type'=>'required|in:darurat,terjadwal','name'=>'required|string|min:3|max:120','phone'=>['required','string','max:20','regex:/^(?:\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'nik'=>'required|digits:16','medical_condition'=>'required|string|max:1500','pickup_location'=>'required|string|max:2000','destination'=>'nullable|string|max:1000',
            'scheduled_at'=>'nullable|date|after:now','service_duration_minutes'=>'nullable|integer|min:30|max:720',
        ]);
        if(!empty($data['request_uuid']) && ($existing=Report::where('request_uuid',$data['request_uuid'])->first())) {
            return response()->json(['message'=>'Permohonan manual sudah dicatat sebelumnya.','code'=>$existing->code]);
        }
        $data['category']=$data['category']??'ambulans';
        if($data['category']!=='ambulans'){ $data['type']='darurat'; $data['scheduled_at']=null; $data['service_duration_minutes']=null; $data['destination']=null; }
        if($data['category']==='ambulans' && $data['type']==='terjadwal' && empty($data['scheduled_at'])) return response()->json(['message'=>'Jadwal jemput wajib diisi untuk ambulans terjadwal.'],422);
        if(!NikValidator::isValid($data['nik'])) return response()->json(['message'=>'NIK tidak valid.','errors'=>['nik'=>['NIK tidak lolos pemeriksaan struktur.']]],422);
        $phone=preg_replace('/\D+/','',$data['phone']); if(str_starts_with($phone,'62'))$phone='0'.substr($phone,2);

        try {
        $report=DB::transaction(function()use($data,$phone,$request){
            $citizen=CitizenService::upsertIdentity($data['nik'],$data['name'],$phone);
            do{$prefix=$data['category']==='bpjs'?'BPJ':($data['category']==='bencana'?'BNC':($data['type']==='darurat'?'LPR':'JDL'));$code=$prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(10));}while(Report::where('code',$code)->exists());
            $start=($data['category']==='ambulans' && $data['type']==='terjadwal')?\Carbon\Carbon::parse($data['scheduled_at']):null;
            $end=$start?->copy()->addMinutes((int)($data['service_duration_minutes']??ScheduleService::DEFAULT_DURATION_MINUTES));
            $report=Report::create([
                'request_uuid'=>$data['request_uuid']??null,'code'=>$code,'tracking_key_hash'=>hash('sha256',$code),'citizen_id'=>$citizen->id,
                'type'=>$data['type'],'category'=>$data['category'],'source'=>$data['source'],'status'=>'menunggu','pickup_location'=>$data['pickup_location'],
                'destination'=>$data['destination']??null,'medical_condition'=>$data['medical_condition'],'scheduled_at'=>$data['scheduled_at']??null,
                'service_start_at'=>$start,'service_end_at'=>$end,'handled_by'=>$request->attributes->get('api_user')->id,
            ]);
            StatusHistoryService::record($request,$report,null,'menunggu');
            return $report;
        });
        } catch(\Throwable $e) {
            if(!empty($data['request_uuid']) && ($existing=Report::where('request_uuid',$data['request_uuid'])->first())) {
                return response()->json(['message'=>'Permohonan manual sudah dicatat sebelumnya.','code'=>$existing->code]);
            }
            throw $e;
        }
        AuditService::log($request,'report.manual_created',$report);
        RevisionService::bump('operations'); Cache::forget('public.bootstrap');
        return response()->json(['message'=>'Permohonan manual berhasil dicatat.','code'=>$report->code],201);
    }

    public function assign(Request $request, Report $report)
    {
        $data=$request->validate(['ambulance_id'=>'nullable|exists:ambulances,id','driver_id'=>'nullable|exists:drivers,id']);
        if($report->category!=='ambulans') return response()->json(['message'=>'Pengaduan BPJS/bencana diproses tanpa penugasan ambulans.'],422);
        if(in_array($report->status,['selesai','ditolak'],true)) return response()->json(['message'=>'Laporan final tidak dapat ditugaskan ulang.'],422);

        $before=$report->only('status','ambulance_id','driver_id','handled_by','service_start_at','service_end_at');
        DB::transaction(function()use($data,$request,$report){
            // Serialize assignment briefly so concurrent staff cannot reserve the same resource.
            SystemRevision::where('scope','operations')->lockForUpdate()->firstOrFail();
            $report=Report::lockForUpdate()->findOrFail($report->id);
            [$start,$end]=ScheduleService::intervalFor($report);
            if(!$report->service_start_at || !$report->service_end_at) $report->fill(['service_start_at'=>$start,'service_end_at'=>$end]);
            $oldAmbulance=$report->ambulance_id ? Ambulance::lockForUpdate()->find($report->ambulance_id) : null;
            $oldDriver=$report->driver_id ? Driver::lockForUpdate()->find($report->driver_id) : null;

            $conflictingAmbulances=ScheduleService::reportConflictQuery($start,$end,$report->id)
                ->whereNotNull('ambulance_id')->select('ambulance_id');
            $amb=Ambulance::query()
                ->when($data['ambulance_id']??null,fn($q,$id)=>$q->whereKey($id))
                ->where('status','!=','maintenance')
                ->when(ScheduleService::isCurrentWindow($start,$end),fn($q)=>$q->whereIn('status',['tersedia','bertugas']))
                ->whereNotIn('id',$conflictingAmbulances)
                ->orderBy('code')->lockForUpdate()->first();
            if(!$amb) abort(422,'Tidak ada ambulans yang tersedia pada rentang jadwal tersebut.');

            $conflictingDrivers=ScheduleService::reportConflictQuery($start,$end,$report->id)
                ->whereNotNull('driver_id')->select('driver_id');
            $drv=Driver::query()
                ->when($data['driver_id']??null,fn($q,$id)=>$q->whereKey($id))
                ->where('status','!=','nonaktif')
                ->when(ScheduleService::isCurrentWindow($start,$end),fn($q)=>$q->whereIn('status',['aktif','bertugas']))
                ->whereNotIn('id',$conflictingDrivers)
                ->orderBy('code')->lockForUpdate()->first();
            if(!$drv) abort(422,'Tidak ada pengemudi yang tersedia pada rentang jadwal tersebut.');

            $fromStatus=$report->status;
            $report->fill(['ambulance_id'=>$amb->id,'driver_id'=>$drv->id,'handled_by'=>$request->attributes->get('api_user')->id,'status'=>'diproses'])->save();
            if($fromStatus!=='diproses') StatusHistoryService::record($request,$report,$fromStatus,'diproses');
            if(ScheduleService::isCurrentWindow($start,$end)){ $amb->update(['status'=>'bertugas']); $drv->update(['status'=>'bertugas']); }
            if($oldAmbulance && $oldAmbulance->id!==$amb->id) ScheduleService::syncAmbulanceStatus($oldAmbulance);
            if($oldDriver && $oldDriver->id!==$drv->id) ScheduleService::syncDriverStatus($oldDriver);
        },3);
        $report->refresh()->load(['ambulance:id,code,plate_number','driver:id,code,name']);
        AuditService::log($request,'report.assigned',$report,['ambulance_id'=>$report->ambulance_id,'driver_id'=>$report->driver_id],$before,$report->only('status','ambulance_id','driver_id','handled_by','service_start_at','service_end_at'));
        RevisionService::bump('operations'); Cache::forget('public.bootstrap');
        return response()->json(['message'=>'Jadwal aman. Ambulans '.$report->ambulance->code.' dan pengemudi '.$report->driver->name.' berhasil ditugaskan tanpa bentrok.']);
    }

    public function updateStatus(Request $request, Report $report)
    {
        $data=$request->validate(['status'=>'required|in:diproses,dijemput,selesai,ditolak','internal_notes'=>'nullable|string|max:2000']);
        $status=$data['status'];
        $before=$report->only('status','internal_notes','completed_at','service_start_at','service_end_at');

        DB::transaction(function()use($request,$report,$status){
            $report=Report::lockForUpdate()->findOrFail($report->id);
            if($report->status===$status) return;
            $allowed=$report->category==='ambulans'
                ? ['menunggu'=>['diproses','ditolak'],'diproses'=>['dijemput','ditolak'],'dijemput'=>['selesai'],'selesai'=>[],'ditolak'=>[]]
                : ['menunggu'=>['diproses','ditolak'],'diproses'=>['selesai','ditolak'],'selesai'=>[],'ditolak'=>[]];
            if(!in_array($status,$allowed[$report->status]??[],true)) abort(422,"Perubahan status {$report->status} ke {$status} tidak diperbolehkan.");
            if($report->category!=='ambulans' && $status==='dijemput') abort(422,'Status dijemput hanya berlaku untuk layanan ambulans.');
            if($status==='dijemput' && (!$report->ambulance_id || !$report->driver_id)) abort(422,'Tetapkan ambulans dan pengemudi sebelum status dijemput.');

            $from=$report->status;
            $report->status=$status;
            $report->internal_notes=$request->input('internal_notes',$report->internal_notes);
            if($status==='dijemput'){
                if(!$report->service_start_at) $report->service_start_at=now();
                if(!$report->service_end_at) $report->service_end_at=now()->addMinutes(ScheduleService::DEFAULT_DURATION_MINUTES);
                $report->ambulance?->update(['status'=>'bertugas']); $report->driver?->update(['status'=>'bertugas']);
            }
            if($status==='selesai'){ $report->completed_at=now(); if(!$report->service_end_at || $report->service_end_at->gt(now())) $report->service_end_at=now(); }
            $report->save();
            StatusHistoryService::record($request,$report,$from,$status,$status==='ditolak'?$request->input('internal_notes'):null);
            if(in_array($status,['selesai','ditolak'],true)){ if($report->ambulance) ScheduleService::syncAmbulanceStatus($report->ambulance); if($report->driver) ScheduleService::syncDriverStatus($report->driver); }
        },3);
        $report->refresh();
        AuditService::log($request,'report.status_changed',$report,['status'=>$status],$before,$report->only('status','internal_notes','completed_at','service_start_at','service_end_at'));
        RevisionService::bump('operations'); Cache::forget('public.bootstrap');
        return response()->json(['message'=>'Status laporan diperbarui.']);
    }

    public function verify(Request $request, Report $report)
    {
        $result=DB::transaction(function()use($request,$report){
            $locked=Report::lockForUpdate()->findOrFail($report->id);
            if($locked->status!=='selesai') return ['message'=>'Hanya laporan selesai yang dapat diverifikasi.','status'=>422,'changed'=>false];
            if($locked->verified_at) return ['message'=>'Administrasi laporan sudah terverifikasi.','status'=>200,'changed'=>false];
            $before=$locked->only('verified_by','verified_at');
            $locked->update(['verified_by'=>$request->attributes->get('api_user')->id,'verified_at'=>now()]);
            AuditService::log($request,'report.verified',$locked,[],$before,$locked->only('verified_by','verified_at'));
            return ['message'=>'Administrasi laporan terverifikasi.','status'=>200,'changed'=>true];
        },3);
        if($result['changed']) RevisionService::bump('operations');
        return response()->json(['message'=>$result['message']],$result['status']);
    }

    public function ktp(Request $request, Report $report)
    {
        if(!$report->ktp_path || !Storage::disk('local')->exists($report->ktp_path)) abort(404);
        AuditService::log($request,'report.ktp_viewed',$report);
        return Storage::disk('local')->download($report->ktp_path,'KTP-'.$report->code.'.'.pathinfo($report->ktp_path,PATHINFO_EXTENSION),['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
