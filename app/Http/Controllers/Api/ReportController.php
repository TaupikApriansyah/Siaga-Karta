<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ambulance;
use App\Models\Driver;
use App\Models\Region;
use App\Models\Report;
use App\Models\SystemRevision;
use App\Models\StatusHistory;
use App\Services\AuditService;
use App\Services\CitizenService;
use App\Services\CitizenTrackingMailService;
use App\Services\NikValidator;
use App\Services\ReportAccessService;
use App\Services\RevisionService;
use App\Services\ScheduleService;
use App\Services\StatusHistoryService;
use App\Services\TrackingCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $filters=$request->validate([
            'status'=>'nullable|in:menunggu,diproses,dijemput,selesai,ditolak',
            'workflow_status'=>'nullable|string|max:40',
            'category'=>['nullable',Rule::in(Report::CATEGORIES)],
            'priority'=>['nullable',Rule::in(Report::PRIORITIES)],
            'region_id'=>'nullable|integer|exists:regions,id',
            'per_page'=>'nullable|integer|min:10|max:100',
            'page'=>'nullable|integer|min:1',
        ]);
        $user=$request->attributes->get('api_user');
        $q=ReportAccessService::scope(Report::query(),$user)
            ->select([
                'id','code','citizen_id','region_id','ambulance_id','driver_id','type','category','priority','source','status','workflow_status',
                'escalation_level','pickup_location','description','medical_condition','rt_number','rw_number','latitude','longitude','assigned_agency',
                'scheduled_at','service_start_at','service_end_at','created_at'
            ])
            ->with([
                'citizen:id,name,phone_last4',
                'region:id,code,short_code,name,level,parent_id','region.parent:id,code,short_code,name,level',
                'ambulance:id,code,plate_number','driver:id,code,name'
            ])->latest();
        if(!empty($filters['status'])) $q->where('status',$filters['status']);
        if(!empty($filters['workflow_status'])) $q->where('workflow_status',$filters['workflow_status']);
        if(!empty($filters['category'])) $q->where('category',$filters['category']);
        if(!empty($filters['priority'])) $q->where('priority',$filters['priority']);
        if(!empty($filters['region_id'])) $q->where('region_id',$filters['region_id']);
        return response()->json($q->paginate(min(max((int)($filters['per_page']??25),10),100)));
    }

    public function show(Request $request, Report $report)
    {
        $this->authorizeReport($request,$report);
        $report->load([
            'citizen','region.parent','ambulance','driver','handler:id,name','submitter:id,name','verifier:id,name','kecamatanVerifier:id,name'
        ]);
        $nik=$report->citizen->getNik();
        $history=StatusHistory::where('subject_type',Report::class)->where('subject_id',$report->id)
            ->with('changedBy:id,name')->latest('id')->limit(30)->get()->map(fn($h)=>[
                'from_status'=>$h->from_status,'to_status'=>$h->to_status,'reason'=>$h->reason,
                'changed_by'=>$h->changedBy?->name,'created_at'=>$h->created_at,
            ]);
        return response()->json(['report'=>[
            'code'=>$report->code,'name'=>$report->citizen->name,'phone'=>$report->citizen->getPhone(),'email'=>$report->citizen->getEmail(),
            'nik_masked'=>substr($nik,0,6).'******'.substr($nik,-4),
            'type'=>$report->type,'category'=>$report->category,'priority'=>$report->priority,'source'=>$report->source,'status'=>$report->status,
            'workflow_status'=>$report->workflow_status,'escalation_level'=>$report->escalation_level,
            'kelurahan'=>$report->region?->name,'kelurahan_id'=>$report->region_id,'kecamatan'=>$report->region?->parent?->name,
            'pickup_location'=>$report->pickup_location,'rt_number'=>$report->rt_number,'rw_number'=>$report->rw_number,
            'latitude'=>$report->latitude,'longitude'=>$report->longitude,'destination'=>$report->destination,
            'medical_condition'=>$report->medical_condition,'description'=>$report->description,
            'scheduled_at'=>$report->scheduled_at,'service_start_at'=>$report->service_start_at,'service_end_at'=>$report->service_end_at,
            'assigned_agency'=>$report->assigned_agency,'agency_referred_at'=>$report->agency_referred_at,
            'kecamatan_verified_at'=>$report->kecamatan_verified_at,'kecamatan_verified_by'=>$report->kecamatanVerifier?->name,
            'ambulance'=>$report->ambulance,'driver'=>$report->driver,'created_at'=>$report->created_at,'status_history'=>$history,
        ]]);
    }

    public function storeManual(Request $request)
    {
        $request->merge([
            'email'=>strtolower(trim((string)$request->input('email'))),
            'priority'=>$request->input('priority','reguler'),
            'type'=>$request->input('type'),
        ]);
        $data=$request->validate([
            'request_uuid'=>'nullable|uuid',
            'source'=>'required|in:datang_langsung,whatsapp,telepon',
            'category'=>['required',Rule::in(Report::CATEGORIES)],
            'priority'=>['required',Rule::in(Report::PRIORITIES)],
            'type'=>[Rule::requiredIf(fn()=> $request->input('category')==='ambulans'),'nullable',Rule::in(['darurat','terjadwal'])],
            'name'=>'required|string|min:3|max:120',
            'phone'=>['required','string','max:20','regex:/^(?:\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'email'=>'required|email:rfc|max:190',
            'nik'=>'required|digits:16',
            'region_id'=>'required|integer|exists:regions,id',
            'pickup_location'=>[Rule::requiredIf(fn()=> $request->input('category')==='ambulans'),'nullable','string','min:5','max:2000'],
            'rt_number'=>'nullable|string|max:10','rw_number'=>'nullable|string|max:10',
            'latitude'=>'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude'=>'nullable|numeric|between:-180,180|required_with:latitude',
            'description'=>'nullable|string|min:3|max:3000','medical_condition'=>'nullable|string|min:3|max:1500',
            'destination'=>'nullable|string|max:1000','scheduled_at'=>'nullable|date|after:now','service_duration_minutes'=>'nullable|integer|min:30|max:720',
        ]);
        if(!empty($data['request_uuid']) && ($existing=Report::where('request_uuid',$data['request_uuid'])->first())) {
            return response()->json(['message'=>'Pengaduan manual sudah dicatat sebelumnya.','code'=>$existing->code]);
        }

        $user=$request->attributes->get('api_user');
        $region=ReportAccessService::allowedKelurahan($user)->with('parent')->whereKey($data['region_id'])->first();
        if(!$region) return response()->json(['message'=>'Kelurahan yang dipilih berada di luar hak akses akun ini.'],403);

        if($data['category']==='ambulans') {
            if(empty($data['medical_condition'])) return response()->json(['message'=>'Kondisi medis wajib diisi untuk layanan ambulans.'],422);
            if($data['type']==='terjadwal' && empty($data['scheduled_at'])) return response()->json(['message'=>'Jadwal jemput wajib diisi untuk ambulans terjadwal.'],422);
        } else {
            if(empty($data['description'])) return response()->json(['message'=>'Isi pengaduan wajib diisi untuk kategori non-ambulans.'],422);
            $data['type']=null; $data['scheduled_at']=null; $data['service_duration_minutes']=null; $data['destination']=null; $data['medical_condition']=null;
        }
        if(!NikValidator::isValid($data['nik'])) return response()->json(['message'=>'NIK tidak valid.','errors'=>['nik'=>['NIK tidak lolos pemeriksaan struktur.']]],422);
        $phone=preg_replace('/\D+/','',$data['phone']); if(str_starts_with($phone,'62'))$phone='0'.substr($phone,2);

        try {
            $report=DB::transaction(function()use($data,$phone,$request,$user,$region){
                $citizen=CitizenService::upsertIdentity($data['nik'],$data['name'],$phone,$data['email']);
                $code=TrackingCodeService::nextForKelurahan($region);
                $start=($data['category']==='ambulans' && $data['type']==='terjadwal')?\Carbon\Carbon::parse($data['scheduled_at']):null;
                $end=$start?->copy()->addMinutes((int)($data['service_duration_minutes']??ScheduleService::DEFAULT_DURATION_MINUTES));
                $report=Report::create([
                    'request_uuid'=>$data['request_uuid']??null,'code'=>$code,'tracking_key_hash'=>hash('sha256',$code),'citizen_id'=>$citizen->id,
                    'region_id'=>$region->id,'type'=>$data['type'],'category'=>$data['category'],'priority'=>$data['priority'],'source'=>$data['source'],
                    'status'=>'menunggu','workflow_status'=>'menunggu_kelurahan','escalation_level'=>'kelurahan','pickup_location'=>$data['pickup_location']??null,
                    'rt_number'=>$data['rt_number']??null,'rw_number'=>$data['rw_number']??null,'latitude'=>$data['latitude']??null,'longitude'=>$data['longitude']??null,
                    'destination'=>$data['destination']??null,'medical_condition'=>$data['medical_condition']??null,'description'=>$data['description']??null,
                    'scheduled_at'=>$data['scheduled_at']??null,'service_start_at'=>$start,'service_end_at'=>$end,'handled_by'=>$user->id,'submitted_by'=>$user->id,
                ]);
                StatusHistoryService::record($request,$report,null,'menunggu_kelurahan');
                return $report;
            },3);
        } catch(\Throwable $e) {
            if(!empty($data['request_uuid']) && ($existing=Report::where('request_uuid',$data['request_uuid'])->first())) {
                return response()->json(['message'=>'Pengaduan manual sudah dicatat sebelumnya.','code'=>$existing->code]);
            }
            throw $e;
        }
        AuditService::log($request,'report.manual_created',$report,['region_id'=>$report->region_id,'category'=>$report->category,'priority'=>$report->priority]);
        RevisionService::bump('operations'); Cache::forget('public.bootstrap');
        $mailSent=CitizenTrackingMailService::sendCreated($report);
        return response()->json([
            'message'=>$mailSent?'Pengaduan berhasil dicatat. Kode pelacakan dikirim ke Gmail warga.':'Pengaduan berhasil dicatat, tetapi notifikasi Gmail belum berhasil dikirim. Kode pelacakan tetap tersedia di sistem.',
            'code'=>$report->code,'email_notification_sent'=>$mailSent,
        ],201);
    }

    public function forwardToKecamatan(Request $request, Report $report)
    {
        $user=$request->attributes->get('api_user');
        $this->authorizeReport($request,$report);
        if($user->role!=='kelurahan') return response()->json(['message'=>'Hanya Karang Taruna tingkat Kelurahan yang dapat mengajukan pengaduan ke Kecamatan.'],403);
        $data=$request->validate(['notes'=>'nullable|string|max:2000']);
        if(!in_array($report->workflow_status,['menunggu_kelurahan','perlu_perbaikan_kelurahan'],true)) {
            return response()->json(['message'=>'Pengaduan ini tidak berada pada tahap verifikasi Kelurahan.'],422);
        }
        $before=$report->only('workflow_status','escalation_level','internal_notes');
        $from=$report->workflow_status;
        $report->update([
            'workflow_status'=>'diajukan_kecamatan','escalation_level'=>'kecamatan','internal_notes'=>$data['notes']??$report->internal_notes,
            'handled_by'=>$user->id,
        ]);
        StatusHistoryService::record($request,$report,$from,'diajukan_kecamatan',$data['notes']??null);
        AuditService::log($request,'report.forwarded_to_kecamatan',$report,[],$before,$report->only('workflow_status','escalation_level','internal_notes'));
        RevisionService::bump('operations');
        return response()->json(['message'=>'Pengaduan berhasil diajukan ke Karang Taruna tingkat Kecamatan untuk validasi dan cross-check.']);
    }

    public function kecamatanDecision(Request $request, Report $report)
    {
        $user=$request->attributes->get('api_user');
        $this->authorizeReport($request,$report);
        if($user->role!=='kecamatan') return response()->json(['message'=>'Aksi validasi ini hanya tersedia untuk Karang Taruna tingkat Kecamatan.'],403);
        $data=$request->validate([
            'decision'=>'required|in:validate,return,reject',
            'notes'=>[Rule::requiredIf(fn()=>in_array($request->input('decision'),['return','reject'],true)),'nullable','string','max:2000'],
        ]);
        if($report->workflow_status!=='diajukan_kecamatan') return response()->json(['message'=>'Pengaduan belum diajukan Kelurahan atau sudah diproses Kecamatan.'],422);

        $before=$report->only('workflow_status','escalation_level','status','internal_notes','kecamatan_verified_by','kecamatan_verified_at','kota_received_at');
        $from=$report->workflow_status;
        $action=''; $message='';
        if($data['decision']==='validate') {
            $report->fill([
                'workflow_status'=>'diterima_kota','escalation_level'=>'kota','kecamatan_verified_by'=>$user->id,
                'kecamatan_verified_at'=>now(),'kota_received_at'=>now(),'internal_notes'=>$data['notes']??$report->internal_notes,
            ]);
            $action='report.validated_by_kecamatan';
            $message='Data telah tervalidasi dan diteruskan ke Karang Taruna tingkat Kota untuk monitoring serta tindak lanjut.';
        } elseif($data['decision']==='return') {
            $report->fill([
                'workflow_status'=>'perlu_perbaikan_kelurahan','escalation_level'=>'kelurahan','internal_notes'=>$data['notes'],
            ]);
            $action='report.returned_to_kelurahan';
            $message='Pengaduan dikembalikan ke Kelurahan untuk perbaikan atau kelengkapan data.';
        } else {
            $report->fill([
                'workflow_status'=>'ditolak_kecamatan','escalation_level'=>'kecamatan','status'=>'ditolak','internal_notes'=>$data['notes'],
            ]);
            $action='report.rejected_by_kecamatan';
            $message='Pengaduan ditolak pada tahap validasi Kecamatan. Alasan penolakan telah dicatat.';
        }
        $report->save();
        StatusHistoryService::record($request,$report,$from,$report->workflow_status,$data['notes']??null);
        AuditService::log($request,$action,$report,['decision'=>$data['decision']],$before,$report->only('workflow_status','escalation_level','status','internal_notes','kecamatan_verified_by','kecamatan_verified_at','kota_received_at'));
        RevisionService::bump('operations'); Cache::forget('public.bootstrap');
        return response()->json(['message'=>$message]);
    }

    public function forwardToOpd(Request $request, Report $report)
    {
        $user=$request->attributes->get('api_user');
        $this->authorizeReport($request,$report);
        if($user->role!=='kota') return response()->json(['message'=>'Hanya Karang Taruna tingkat Kota yang dapat meneruskan pengaduan ke OPD/instansi terkait.'],403);
        $data=$request->validate(['agency'=>'required|string|min:2|max:160','notes'=>'nullable|string|max:2000']);
        if(!in_array($report->workflow_status,['diterima_kota','diteruskan_opd'],true)) return response()->json(['message'=>'Pengaduan belum lolos validasi Kecamatan.'],422);
        $before=$report->only('workflow_status','escalation_level','assigned_agency','agency_referred_at','status','internal_notes');
        $from=$report->workflow_status;
        $report->update([
            'workflow_status'=>'diteruskan_opd','escalation_level'=>'opd','assigned_agency'=>$data['agency'],'agency_referred_at'=>now(),
            'status'=>$report->status==='menunggu'?'diproses':$report->status,'internal_notes'=>$data['notes']??$report->internal_notes,'handled_by'=>$user->id,
        ]);
        StatusHistoryService::record($request,$report,$from,'diteruskan_opd',$data['notes']??null);
        AuditService::log($request,'report.forwarded_to_opd',$report,['agency'=>$data['agency']],$before,$report->only('workflow_status','escalation_level','assigned_agency','agency_referred_at','status','internal_notes'));
        RevisionService::bump('operations'); Cache::forget('public.bootstrap');
        return response()->json(['message'=>'Pengaduan berhasil diteruskan ke '.$data['agency'].'. Status dan jejak eskalasi telah diperbarui.']);
    }

    public function assign(Request $request, Report $report)
    {
        $user=$request->attributes->get('api_user');
        $this->authorizeReport($request,$report);
        if($user->role!=='kota') return response()->json(['message'=>'Penugasan operasional setelah validasi hanya dapat dilakukan oleh Karang Taruna tingkat Kota.'],403);
        $data=$request->validate(['ambulance_id'=>'nullable|exists:ambulances,id','driver_id'=>'nullable|exists:drivers,id']);
        if($report->category!=='ambulans') return response()->json(['message'=>'Kategori ini diproses tanpa penugasan ambulans.'],422);
        if($report->workflow_status!=='diterima_kota' && $report->workflow_status!=='diteruskan_opd') return response()->json(['message'=>'Laporan ambulans harus lolos validasi Kecamatan terlebih dahulu.'],422);
        if(in_array($report->status,['selesai','ditolak'],true)) return response()->json(['message'=>'Laporan final tidak dapat ditugaskan ulang.'],422);

        $before=$report->only('status','ambulance_id','driver_id','handled_by','service_start_at','service_end_at');
        DB::transaction(function()use($data,$request,$report){
            SystemRevision::where('scope','operations')->lockForUpdate()->firstOrCreate(['scope'=>'operations'],['version'=>1,'updated_at'=>now()]);
            $report=Report::lockForUpdate()->findOrFail($report->id);
            [$start,$end]=ScheduleService::intervalFor($report);
            if(!$report->service_start_at || !$report->service_end_at) $report->fill(['service_start_at'=>$start,'service_end_at'=>$end]);
            $oldAmbulance=$report->ambulance_id ? Ambulance::lockForUpdate()->find($report->ambulance_id) : null;
            $oldDriver=$report->driver_id ? Driver::lockForUpdate()->find($report->driver_id) : null;

            $conflictingAmbulances=ScheduleService::reportConflictQuery($start,$end,$report->id)->whereNotNull('ambulance_id')->select('ambulance_id');
            $amb=Ambulance::query()->when($data['ambulance_id']??null,fn($q,$id)=>$q->whereKey($id))->where('status','!=','maintenance')
                ->when(ScheduleService::isCurrentWindow($start,$end),fn($q)=>$q->whereIn('status',['tersedia','bertugas']))
                ->whereNotIn('id',$conflictingAmbulances)->orderBy('code')->lockForUpdate()->first();
            if(!$amb) abort(422,'Tidak ada ambulans yang tersedia pada rentang jadwal tersebut.');

            $conflictingDrivers=ScheduleService::reportConflictQuery($start,$end,$report->id)->whereNotNull('driver_id')->select('driver_id');
            $drv=Driver::query()->when($data['driver_id']??null,fn($q,$id)=>$q->whereKey($id))->where('status','!=','nonaktif')
                ->when(ScheduleService::isCurrentWindow($start,$end),fn($q)=>$q->whereIn('status',['aktif','bertugas']))
                ->whereNotIn('id',$conflictingDrivers)->orderBy('code')->lockForUpdate()->first();
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
        return response()->json(['message'=>'Ambulans '.$report->ambulance->code.' dan pengemudi '.$report->driver->name.' berhasil ditugaskan tanpa bentrok jadwal.']);
    }

    public function updateStatus(Request $request, Report $report)
    {
        $user=$request->attributes->get('api_user');
        $this->authorizeReport($request,$report);
        if($user->role!=='kota') return response()->json(['message'=>'Perubahan status operasional setelah validasi hanya dapat dilakukan Karang Taruna tingkat Kota.'],403);
        $data=$request->validate(['status'=>'required|in:diproses,dijemput,selesai,ditolak','internal_notes'=>'nullable|string|max:2000']);
        if(!in_array($report->workflow_status,['diterima_kota','diteruskan_opd'],true) && $report->workflow_status!=='selesai') {
            return response()->json(['message'=>'Status operasional baru dapat diubah setelah pengaduan lolos validasi Kecamatan dan diterima Karang Taruna tingkat Kota.'],422);
        }
        $status=$data['status'];
        $before=$report->only('status','workflow_status','internal_notes','completed_at','service_start_at','service_end_at');

        DB::transaction(function()use($request,$report,$status){
            $report=Report::lockForUpdate()->findOrFail($report->id);
            if($report->status===$status) return;
            $allowed=$report->category==='ambulans'
                ? ['menunggu'=>['diproses','ditolak'],'diproses'=>['dijemput','selesai','ditolak'],'dijemput'=>['selesai'],'selesai'=>[],'ditolak'=>[]]
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
            if($status==='selesai'){
                $report->completed_at=now();
                $report->workflow_status='selesai';
                $report->escalation_level='kota';
                if(!$report->service_end_at || $report->service_end_at->gt(now())) $report->service_end_at=now();
            }
            $report->save();
            StatusHistoryService::record($request,$report,$from,$status,$status==='ditolak'?$request->input('internal_notes'):null);
            if(in_array($status,['selesai','ditolak'],true)){ if($report->ambulance) ScheduleService::syncAmbulanceStatus($report->ambulance); if($report->driver) ScheduleService::syncDriverStatus($report->driver); }
        },3);
        $report->refresh();
        AuditService::log($request,'report.status_changed',$report,['status'=>$status],$before,$report->only('status','workflow_status','internal_notes','completed_at','service_start_at','service_end_at'));
        RevisionService::bump('operations'); Cache::forget('public.bootstrap');
        return response()->json(['message'=>'Status operasional laporan diperbarui.']);
    }

    public function verify(Request $request, Report $report)
    {
        $user=$request->attributes->get('api_user');
        $this->authorizeReport($request,$report);
        if($user->role!=='kota') return response()->json(['message'=>'Verifikasi administrasi final hanya tersedia untuk Karang Taruna tingkat Kota.'],403);
        $result=DB::transaction(function()use($request,$report){
            $locked=Report::lockForUpdate()->findOrFail($report->id);
            if($locked->status!=='selesai') return ['message'=>'Hanya laporan selesai yang dapat diverifikasi.','status'=>422,'changed'=>false];
            if($locked->verified_at) return ['message'=>'Administrasi laporan sudah terverifikasi.','status'=>200,'changed'=>false];
            $before=$locked->only('verified_by','verified_at');
            $locked->update(['verified_by'=>$request->attributes->get('api_user')->id,'verified_at'=>now()]);
            AuditService::log($request,'report.verified',$locked,[],$before,$locked->only('verified_by','verified_at'));
            return ['message'=>'Administrasi laporan terverifikasi oleh Karang Taruna tingkat Kota.','status'=>200,'changed'=>true];
        },3);
        if($result['changed']) RevisionService::bump('operations');
        return response()->json(['message'=>$result['message']],$result['status']);
    }

    public function ktp(Request $request, Report $report)
    {
        $this->authorizeReport($request,$report);
        if(!$report->ktp_path || !Storage::disk('local')->exists($report->ktp_path)) abort(404);
        AuditService::log($request,'report.ktp_viewed',$report);
        return Storage::disk('local')->download($report->ktp_path,'KTP-'.$report->code.'.'.pathinfo($report->ktp_path,PATHINFO_EXTENSION),['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }

    private function authorizeReport(Request $request, Report $report): void
    {
        $user=$request->attributes->get('api_user');
        abort_unless($user && ReportAccessService::canAccess($user,$report),404);
    }
}
