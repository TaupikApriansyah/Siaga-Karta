<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Ambulance;
use App\Models\Citizen;
use App\Models\Driver;
use App\Models\Report;
use App\Services\AuditService;
use App\Services\NikValidator;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class ReportController extends Controller
{
    public function index(Request $request)
    {
        $q=Report::with(['citizen:id,name,phone_last4','ambulance:id,code,plate_number','driver:id,code,name'])->latest();
        if($request->filled('status')) $q->where('status',$request->status);
        return response()->json($q->paginate(min((int)$request->input('per_page',25),100)));
    }
    public function show(Report $report)
    {
        $report->load(['citizen','ambulance','driver','handler:id,name','verifier:id,name']);
        return response()->json(['report'=>[
            'code'=>$report->code,'name'=>$report->citizen->name,'phone'=>$report->citizen->getPhone(),'nik_masked'=>substr($report->citizen->getNik(),0,6).'******'.substr($report->citizen->getNik(),-4),
            'type'=>$report->type,'category'=>$report->category,'source'=>$report->source,'status'=>$report->status,'pickup_location'=>$report->pickup_location,'destination'=>$report->destination,
            'medical_condition'=>$report->medical_condition,'scheduled_at'=>$report->scheduled_at,'service_start_at'=>$report->service_start_at,'service_end_at'=>$report->service_end_at,
            'ambulance'=>$report->ambulance,'driver'=>$report->driver,'created_at'=>$report->created_at,
        ]]);
    }
    public function storeManual(Request $request)
    {
        $data=$request->validate([
            'source'=>'required|in:datang_langsung,whatsapp,telepon','category'=>'nullable|in:ambulans,bpjs,bencana','type'=>'required|in:darurat,terjadwal','name'=>'required|string|min:3|max:120',
            'phone'=>['required','string','max:20','regex:/^(?:\+62|62|0)8[1-9][0-9]{6,11}$/'],'nik'=>'required|digits:16','medical_condition'=>'required|string|max:1500',
            'pickup_location'=>'required|string|max:2000','destination'=>'nullable|string|max:1000','scheduled_at'=>'nullable|date|after:now',
            'service_duration_minutes'=>'nullable|integer|min:30|max:720',
        ]);
        $data['category']=$data['category']??'ambulans';
        if($data['category']!=='ambulans'){ $data['type']='darurat'; $data['scheduled_at']=null; $data['service_duration_minutes']=null; $data['destination']=null; }
        if($data['category']==='ambulans' && $data['type']==='terjadwal' && empty($data['scheduled_at'])) return response()->json(['message'=>'Jadwal jemput wajib diisi untuk ambulans terjadwal.'],422);
        if(!NikValidator::isValid($data['nik'])) return response()->json(['message'=>'NIK tidak valid.','errors'=>['nik'=>['NIK tidak lolos pemeriksaan struktur.']]],422);
        $phone=preg_replace('/\D+/','',$data['phone']); if(str_starts_with($phone,'62'))$phone='0'.substr($phone,2);
        $report=DB::transaction(function()use($data,$phone,$request){
            $citizen=Citizen::where('nik_hash',Citizen::fingerprint($data['nik']))->first();
            if(!$citizen){$citizen=new Citizen(['name'=>$data['name']]);$citizen->setNik($data['nik']);$citizen->setPhone($phone);$citizen->save();}else{$citizen->name=$data['name'];$citizen->setPhone($phone);$citizen->save();}
            do{$prefix=$data['category']==='bpjs'?'BPJ':($data['category']==='bencana'?'BNC':($data['type']==='darurat'?'LPR':'JDL'));$code=$prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(10));}while(Report::where('code',$code)->exists());
            $start=($data['category']==='ambulans' && $data['type']==='terjadwal')?\Carbon\Carbon::parse($data['scheduled_at']):null;
            $end=$start?->copy()->addMinutes((int)($data['service_duration_minutes']??ScheduleService::DEFAULT_DURATION_MINUTES));
            return Report::create(['code'=>$code,'tracking_key_hash'=>hash('sha256',$code),'citizen_id'=>$citizen->id,'type'=>$data['type'],'category'=>$data['category'],'source'=>$data['source'],'status'=>'menunggu','pickup_location'=>$data['pickup_location'],'destination'=>$data['destination']??null,'medical_condition'=>$data['medical_condition'],'scheduled_at'=>$data['scheduled_at']??null,'service_start_at'=>$start,'service_end_at'=>$end,'handled_by'=>$request->attributes->get('api_user')->id]);
        });
        AuditService::log($request,'report.manual_created',$report);
        return response()->json(['message'=>'Permohonan manual berhasil dicatat.','code'=>$report->code],201);
    }
    public function assign(Request $request, Report $report)
    {
        $data=$request->validate(['ambulance_id'=>'nullable|exists:ambulances,id','driver_id'=>'nullable|exists:drivers,id']);
        if($report->category!=='ambulans') return response()->json(['message'=>'Pengaduan BPJS/bencana diproses tanpa penugasan ambulans.'],422);
        DB::transaction(function()use($data,$request,$report){
            $report=Report::lockForUpdate()->findOrFail($report->id);
            [$start,$end]=ScheduleService::intervalFor($report);
            if(!$report->service_start_at || !$report->service_end_at) $report->fill(['service_start_at'=>$start,'service_end_at'=>$end]);

            $ambulances=Ambulance::query()->when($data['ambulance_id']??null,fn($q,$id)=>$q->whereKey($id))->where('status','!=','maintenance')->orderBy('code')->lockForUpdate()->get();
            $amb=$ambulances->first(fn($a)=>!ScheduleService::ambulanceHasConflict($a->id,$start,$end,$report->id) && (!ScheduleService::isCurrentWindow($start,$end) || $a->status==='tersedia'));
            if(!$amb) abort(422,'Tidak ada ambulans yang tersedia pada rentang jadwal tersebut. Silakan pilih waktu lain atau unit lain.');

            $drivers=Driver::query()->when($data['driver_id']??null,fn($q,$id)=>$q->whereKey($id))->where('status','!=','nonaktif')->orderBy('code')->lockForUpdate()->get();
            $drv=$drivers->first(fn($d)=>!ScheduleService::driverHasConflict($d->id,$start,$end,$report->id) && (!ScheduleService::isCurrentWindow($start,$end) || $d->status==='aktif'));
            if(!$drv) abort(422,'Tidak ada driver yang tersedia pada rentang jadwal tersebut.');

            $report->fill(['ambulance_id'=>$amb->id,'driver_id'=>$drv->id,'handled_by'=>$request->attributes->get('api_user')->id,'status'=>'diproses'])->save();
            if(ScheduleService::isCurrentWindow($start,$end)){ $amb->update(['status'=>'bertugas']); $drv->update(['status'=>'bertugas']); }
        });
        $report->refresh()->load(['ambulance:id,code,plate_number','driver:id,code,name']);
        AuditService::log($request,'report.assigned',$report,['ambulance_id'=>$report->ambulance_id,'driver_id'=>$report->driver_id]);
        return response()->json(['message'=>'Jadwal aman. Ambulans '.$report->ambulance->code.' dan driver '.$report->driver->name.' berhasil ditugaskan tanpa bentrok.']);
    }
    public function updateStatus(Request $request, Report $report)
    {
        $status=$request->validate(['status'=>'required|in:diproses,dijemput,selesai,ditolak','internal_notes'=>'nullable|string|max:2000'])['status'];
        DB::transaction(function()use($request,$report,$status){
            $report=Report::lockForUpdate()->findOrFail($report->id);
            if($report->category!=='ambulans' && $status==='dijemput') abort(422,'Status dijemput hanya berlaku untuk layanan ambulans.');
            $report->status=$status; $report->internal_notes=$request->input('internal_notes',$report->internal_notes);
            if($status==='dijemput'){
                if(!$report->service_start_at) $report->service_start_at=now();
                if(!$report->service_end_at) $report->service_end_at=now()->addMinutes(ScheduleService::DEFAULT_DURATION_MINUTES);
                $report->ambulance?->update(['status'=>'bertugas']); $report->driver?->update(['status'=>'bertugas']);
            }
            if($status==='selesai'){ $report->completed_at=now(); if(!$report->service_end_at || $report->service_end_at->gt(now())) $report->service_end_at=now(); }
            $report->save();
            if(in_array($status,['selesai','ditolak'],true)){ if($report->ambulance) ScheduleService::syncAmbulanceStatus($report->ambulance); if($report->driver) ScheduleService::syncDriverStatus($report->driver); }
        });
        AuditService::log($request,'report.status_changed',$report,['status'=>$status]); return response()->json(['message'=>'Status laporan diperbarui.']);
    }
    public function verify(Request $request, Report $report)
    {
        if($report->status!=='selesai') return response()->json(['message'=>'Hanya laporan selesai yang dapat diverifikasi.'],422);
        $report->update(['verified_by'=>$request->attributes->get('api_user')->id,'verified_at'=>now()]); AuditService::log($request,'report.verified',$report);
        return response()->json(['message'=>'Administrasi laporan terverifikasi.']);
    }
    public function ktp(Request $request, Report $report)
    {
        if(!$report->ktp_path || !Storage::disk('local')->exists($report->ktp_path)) abort(404);
        AuditService::log($request,'report.ktp_viewed',$report);
        return Storage::disk('local')->download($report->ktp_path,'KTP-'.$report->code.'.'.pathinfo($report->ktp_path,PATHINFO_EXTENSION));
    }
}
